<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\Grade;
use App\Models\GradingScale;
use App\Models\ReportCard;
use App\Models\ReportCardItem;
use App\Models\Section;
use App\Models\SectionSubjectTeacher;
use App\Models\Student;
use App\Models\StudentSubjectSelection;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use App\Services\NotificationPipelineService;
use App\Tenancy\Exceptions\ClosedAcademicYearException;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdfWrapper;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ReportCardService
{
    public function __construct(
        protected AttendanceService $attendanceService
    ) {}

    /**
     * Record or update grades for an exam and subject across students.
     */
    public function recordGrades(
        Exam $exam,
        Subject $subject,
        array $gradesData,
        User $marker,
        ?Section $section = null
    ): array {
        if ($exam->academicYear && $exam->academicYear->isClosed()) {
            throw new ClosedAcademicYearException('Cannot enter grades for a closed academic year.');
        }

        $schoolId = $exam->school_id;
        $term = $exam->term;

        $recordedGrades = [];
        $affectedStudents = [];

        DB::beginTransaction();
        try {
            foreach ($gradesData as $item) {
                $studentId = (int) $item['student_id'];
                $marksObtained = (float) $item['marks_obtained'];
                $maxMarks = isset($item['max_marks']) ? (float) $item['max_marks'] : (float) $exam->max_marks;
                $remarks = $item['remarks'] ?? null;
                $secId = $item['section_id'] ?? ($section?->id);

                $grade = Grade::updateOrCreate(
                    [
                        'school_id' => $schoolId,
                        'student_id' => $studentId,
                        'subject_id' => $subject->id,
                        'exam_id' => $exam->id,
                    ],
                    [
                        'section_id' => $secId,
                        'marks_obtained' => $marksObtained,
                        'max_marks' => $maxMarks,
                        'entered_by' => $marker->id,
                        'remarks' => $remarks,
                    ]
                );

                $recordedGrades[] = $grade;

                $student = Student::withoutGlobalScopes()
                    ->where('school_id', $schoolId)
                    ->find($studentId);

                if ($student) {
                    $affectedStudents[$studentId] = $student;
                }
            }

            // Auto-aggregate report cards for all affected students
            $gradingScale = GradingScale::getDefaultScale($schoolId);
            $sectionsToRank = [];

            foreach ($affectedStudents as $student) {
                $reportCard = $this->aggregateStudentReportCard($student, $term, $gradingScale, $marker);
                if ($reportCard->section_id) {
                    $sectionsToRank[$reportCard->section_id] = $reportCard->section;
                }
            }

            // Update ranks for affected sections
            foreach ($sectionsToRank as $sec) {
                if ($sec) {
                    $this->updateSectionRanks($sec, $term);
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return [
            'count' => count($recordedGrades),
            'grades' => $recordedGrades,
            'exam_id' => $exam->id,
            'subject_id' => $subject->id,
        ];
    }

    /**
     * Aggregate all subjects and exams for a student for a term into a grade-based report card.
     */
    public function aggregateStudentReportCard(
        Student $student,
        Term $term,
        ?GradingScale $gradingScale = null,
        ?User $generatedBy = null
    ): ReportCard {
        $schoolId = $student->school_id;

        // Resolve student's section for this term/academic year
        $section = $student->currentSection;
        if (! $section || (int) $section->academic_year_id !== (int) $term->academic_year_id) {
            $enrollment = $student->enrollments()
                ->where('academic_year_id', $term->academic_year_id)
                ->first();
            if ($enrollment && $enrollment->section) {
                $section = $enrollment->section;
            } else {
                $assignment = \App\Models\StudentSectionAssignment::withoutGlobalScopes()
                    ->where('student_id', $student->id)
                    ->where('academic_year_id', $term->academic_year_id)
                    ->first();
                if ($assignment && $assignment->section) {
                    $section = $assignment->section;
                }
            }
        }

        if (! $section) {
            throw new HttpException(422, "Student is not assigned to a section for academic year: {$term->academicYear->name}");
        }

        $gradeLevelId = $section->grade_level_id;
        $gradingScale = $gradingScale ?? GradingScale::getDefaultScale($schoolId);

        // Find or create report card
        $reportCard = ReportCard::withoutGlobalScopes()->firstOrNew([
            'school_id' => $schoolId,
            'student_id' => $student->id,
            'term_id' => $term->id,
        ]);

        $reportCard->section_id = $section->id;
        $reportCard->academic_year_id = $term->academic_year_id;
        $reportCard->grading_scale_id = $gradingScale->id;
        if (! $reportCard->exists) {
            $reportCard->status = 'draft';
        }
        if ($generatedBy) {
            $reportCard->generated_by = $generatedBy->id;
        }
        $reportCard->save();

        // Fetch subjects applicable to this grade level (including course-bridged school-wide subjects)
        $allGradeSubjects = Subject::withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->where(function ($q) use ($gradeLevelId) {
                $q->where('grade_level_id', $gradeLevelId)
                  ->orWhereNull('grade_level_id');
            })
            ->get();

        // Branching: core subjects are implicit for all; electives only if actively enrolled
        $enrolledElectiveIds = StudentSubjectSelection::withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->where('student_id', $student->id)
            ->where('academic_year_id', $term->academic_year_id)
            ->where('status', 'enrolled')
            ->pluck('subject_id')
            ->all();

        $subjects = $allGradeSubjects->filter(function ($subject) use ($enrolledElectiveIds) {
            if (! $subject->is_elective) {
                return true;
            }
            return in_array($subject->id, $enrolledElectiveIds);
        });

        // Prune any stale report card items for subjects the student is not enrolled in
        $validSubjectIds = $subjects->pluck('id')->all();
        ReportCardItem::where('report_card_id', $reportCard->id)
            ->whereNotIn('subject_id', $validSubjectIds)
            ->delete();

        // Fetch exams scheduled for this term and grade level
        $exams = Exam::withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->where('term_id', $term->id)
            ->where('grade_level_id', $gradeLevelId)
            ->get();

        $examIds = $exams->pluck('id')->all();

        $subjectSubmittedCount = 0;

        foreach ($subjects as $subject) {
            // Find teacher assigned to this section & course/subject
            $teacherUserId = null;
            if ($subject->course_id) {
                $assignment = SectionSubjectTeacher::withoutGlobalScopes()
                    ->where('school_id', $schoolId)
                    ->where('section_id', $section->id)
                    ->where('course_id', $subject->course_id)
                    ->first();
                if ($assignment && $assignment->staff) {
                    $teacherUserId = $assignment->staff->user_id;
                }
            }

            if (! $teacherUserId && $section->homeroom_teacher_id) {
                $teacherUserId = $section->homeroom_teacher_id;
            }

            // Find student's grades for this subject across exams of this term
            $grades = Grade::withoutGlobalScopes()
                ->where('school_id', $schoolId)
                ->where('student_id', $student->id)
                ->where('subject_id', $subject->id)
                ->whereIn('exam_id', $examIds)
                ->with('exam')
                ->get();

            if ($grades->isNotEmpty()) {
                $subjectSubmittedCount++;

                $totalWeightedPct = 0.0;
                $totalWeight = 0.0;
                $examBreakdown = [];

                foreach ($grades as $g) {
                    $examWeight = $g->exam && (float) $g->exam->weight > 0 ? (float) $g->exam->weight : 1.0;
                    $examMax = max(1.0, (float) $g->max_marks);
                    $pct = ((float) $g->marks_obtained / $examMax) * 100;

                    $totalWeightedPct += ($pct * $examWeight);
                    $totalWeight += $examWeight;

                    $examBreakdown[] = [
                        'exam_id' => $g->exam_id,
                        'exam_name' => $g->exam?->name ?? 'Exam',
                        'exam_type' => $g->exam?->type ?? 'assessment',
                        'weight' => $examWeight,
                        'marks_obtained' => (float) $g->marks_obtained,
                        'max_marks' => (float) $g->max_marks,
                        'percentage' => round($pct, 2),
                    ];
                }

                $finalSubjectPct = $totalWeight > 0 ? round($totalWeightedPct / $totalWeight, 2) : 0.0;
                $gradeCalc = $gradingScale->calculateGrade($finalSubjectPct);

                ReportCardItem::updateOrCreate(
                    [
                        'report_card_id' => $reportCard->id,
                        'subject_id' => $subject->id,
                    ],
                    [
                        'teacher_id' => $teacherUserId,
                        'marks_obtained' => $finalSubjectPct,
                        'max_marks' => 100.00,
                        'percentage' => $finalSubjectPct,
                        'letter_grade' => $gradeCalc['grade'],
                        'gpa_point' => $gradeCalc['gpa_point'],
                        'teacher_remarks' => $gradeCalc['description'],
                        'exam_breakdown' => $examBreakdown,
                    ]
                );
            }
        }

        // Check if all subjects have submissions
        $allTeachersSubmitted = ($subjects->isNotEmpty() && $subjectSubmittedCount >= $subjects->count());

        // Refresh items and compute aggregates
        $items = ReportCardItem::where('report_card_id', $reportCard->id)->get();
        $totalMarksObtained = $items->sum('marks_obtained');
        $totalMaxMarks = $items->sum('max_marks');
        $avgPct = $items->isNotEmpty() ? round($items->avg('percentage'), 2) : 0.0;
        $overallScale = $gradingScale->calculateGrade($avgPct);

        // Attendance summary
        $attSummaryData = $this->attendanceService->getStudentAttendanceSummary(
            $student,
            $term->academic_year_id,
            $term->id
        );

        $reportCard->update([
            'total_marks_obtained' => round($totalMarksObtained, 2),
            'total_max_marks' => round($totalMaxMarks, 2),
            'average_percentage' => $avgPct,
            'overall_grade' => $overallScale['grade'],
            'gpa' => $overallScale['gpa_point'],
            'attendance_summary' => $attSummaryData['summary'] ?? null,
            'all_teachers_submitted' => $allTeachersSubmitted,
        ]);

        return $reportCard->fresh(['items.subject', 'items.teacher', 'student.user', 'section', 'term', 'academicYear', 'gradingScale']);
    }

    /**
     * Batch aggregate report cards for an entire section.
     */
    public function aggregateSectionReportCards(
        Section $section,
        Term $term,
        ?GradingScale $gradingScale = null,
        ?User $generatedBy = null
    ): Collection {
        $schoolId = $section->school_id;
        $gradingScale = $gradingScale ?? GradingScale::getDefaultScale($schoolId);

        $students = Student::withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->where(function ($q) use ($section) {
                $q->where('current_section_id', $section->id)
                  ->orWhereHas('enrollments', function ($eq) use ($section) {
                      $eq->where('section_id', $section->id)
                         ->where('academic_year_id', $section->academic_year_id);
                  })
                  ->orWhereIn('id', function ($sub) use ($section) {
                      $sub->select('student_id')
                          ->from('student_section_assignments')
                          ->where('section_id', $section->id);
                  });
            })
            ->get()
            ->unique('id');

        $reportCards = collect();

        DB::beginTransaction();
        try {
            foreach ($students as $student) {
                $reportCards->push(
                    $this->aggregateStudentReportCard($student, $term, $gradingScale, $generatedBy)
                );
            }

            $this->updateSectionRanks($section, $term);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return ReportCard::withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->where('section_id', $section->id)
            ->where('term_id', $term->id)
            ->with(['items.subject', 'student.user', 'gradingScale'])
            ->orderBy('rank_in_section')
            ->get();
    }

    /**
     * Update rank in section for all report cards in a section for a term.
     */
    public function updateSectionRanks(Section $section, Term $term): void
    {
        $cards = ReportCard::withoutGlobalScopes()
            ->where('school_id', $section->school_id)
            ->where('section_id', $section->id)
            ->where('term_id', $term->id)
            ->orderByDesc('average_percentage')
            ->orderByDesc('total_marks_obtained')
            ->get();

        $totalCount = $cards->count();
        $rank = 1;

        foreach ($cards as $card) {
            $card->update([
                'rank_in_section' => $rank++,
                'total_students_in_section' => $totalCount,
            ]);
        }
    }

    /**
     * Publish a report card so parents and students can view and download it.
     */
    public function publishReportCard(ReportCard $reportCard, ?string $principalRemarks = null): ReportCard
    {
        $payload = [
            'status' => 'published',
            'published_at' => now(),
        ];

        if ($principalRemarks !== null) {
            $payload['principal_remarks'] = $principalRemarks;
        }

        $reportCard->update($payload);
        $fresh = $reportCard->fresh();

        // Trigger unified cross-cutting notification pipeline (in-app + email if enabled)
        app(NotificationPipelineService::class)->notifyReportCardPublished($fresh);

        return $fresh;
    }

    /**
     * Bulk publish all report cards in a section for a given term.
     */
    public function publishSectionReportCards(Section $section, Term $term): int
    {
        $cards = ReportCard::withoutGlobalScopes()
            ->where('school_id', $section->school_id)
            ->where('section_id', $section->id)
            ->where('term_id', $term->id)
            ->get();

        $pipeline = app(NotificationPipelineService::class);
        $count = 0;

        foreach ($cards as $card) {
            $card->update([
                'status' => 'published',
                'published_at' => now(),
            ]);
            $pipeline->notifyReportCardPublished($card);
            $count++;
        }

        return $count;
    }

    /**
     * Generate a PDF instance for a report card matching the school's configured scale.
     */
    public function generatePdf(ReportCard $reportCard): DomPdfWrapper
    {
        $reportCard->loadMissing([
            'school',
            'student.user',
            'section.gradeLevel',
            'section.homeroomTeacher',
            'term.academicYear',
            'gradingScale',
            'items.subject',
            'items.teacher',
        ]);

        $school = $reportCard->school;
        $student = $reportCard->student;
        $section = $reportCard->section;
        $term = $reportCard->term;
        $academicYear = $reportCard->academicYear ?? $term->academicYear;
        $gradingScale = $reportCard->gradingScale ?? GradingScale::getDefaultScale($reportCard->school_id);
        $items = $reportCard->items;
        $attendanceSummary = $reportCard->attendance_summary ?? [];

        $data = [
            'reportCard' => $reportCard,
            'school' => $school,
            'student' => $student,
            'section' => $section,
            'term' => $term,
            'academicYear' => $academicYear,
            'gradingScale' => $gradingScale,
            'items' => $items,
            'attendanceSummary' => $attendanceSummary,
        ];

        return Pdf::loadView('pdf.report_card', $data)
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'defaultFont' => 'DejaVu Sans',
            ]);
    }
}
