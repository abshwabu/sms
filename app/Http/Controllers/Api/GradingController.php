<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreGradeRequest;
use App\Http\Responses\ApiResponse;
use App\Http\Traits\HasApiResponse;
use App\Models\Course;
use App\Models\Exam;
use App\Models\Grade;
use App\Models\GradingScale;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Services\ReportCardService;
use App\Tenancy\TenantManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class GradingController extends Controller
{
    use HasApiResponse;

    /**
     * List subjects, optionally filtered by grade level.
     * Auto-bridges courses into scheduleable subjects if not yet mapped.
     */
    public function indexSubjects(Request $request): JsonResponse
    {
        $tenantManager = app(TenantManager::class);
        if ($tenantManager->hasTenant()) {
            $schoolId = $tenantManager->getTenantId();

            // Auto-bridge any courses in active school that lack a Subject record
            $unlinkedCourses = Course::withoutGlobalScopes()
                ->where('school_id', $schoolId)
                ->whereDoesntHave('subjects')
                ->get();

            foreach ($unlinkedCourses as $course) {
                Subject::firstOrCreate(
                    [
                        'school_id' => $schoolId,
                        'course_id' => $course->id,
                    ],
                    [
                        'grade_level_id' => null,
                        'name' => $course->name,
                        'code' => $course->code,
                        'credit_hours' => 1.0,
                        'description' => $course->description,
                        'is_elective' => false,
                    ]
                );
            }
        }

        $query = Subject::with(['gradeLevel', 'course']);

        if ($request->filled('grade_level_id')) {
            $gradeLevelId = (int) $request->query('grade_level_id');
            $query->where(function ($q) use ($gradeLevelId) {
                $q->where('grade_level_id', $gradeLevelId)
                  ->orWhereNull('grade_level_id');
            });
        }

        $subjects = $query->orderBy('name')->get();

        if (! $request->filled('grade_level_id')) {
            $subjects = $subjects->unique(function ($s) {
                return $s->course_id ? "course_{$s->course_id}" : "subject_{$s->id}";
            })->values();
        }

        return $this->respondWithSuccess($subjects, 'Subjects retrieved successfully.');
    }

    /**
     * Create a new subject for a grade level.
     */
    public function storeSubject(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->isSchoolAdmin() && ! $user->isSuperAdmin()) {
            return ApiResponse::error('Only administrators can manage subjects.', 'FORBIDDEN', Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'grade_level_id' => ['nullable', 'integer', 'exists:grade_levels,id'],
            'course_id' => ['nullable', 'integer', 'exists:courses,id'],
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:30'],
            'credit_hours' => ['nullable', 'numeric', 'min:0.5', 'max:20'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_elective' => ['nullable', 'boolean'],
        ]);

        $subject = Subject::create(array_merge($validated, [
            'school_id' => $user->school_id,
        ]));

        return $this->respondWithSuccess($subject->load(['gradeLevel', 'course']), 'Subject created successfully.', Response::HTTP_CREATED);
    }

    /**
     * List exams, optionally filtered by term or grade level.
     */
    public function indexExams(Request $request): JsonResponse
    {
        $query = Exam::with(['term', 'academicYear', 'gradeLevel']);

        if ($request->filled('term_id')) {
            $query->where('term_id', $request->query('term_id'));
        }

        if ($request->filled('grade_level_id')) {
            $query->where('grade_level_id', $request->query('grade_level_id'));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->query('type'));
        }

        $exams = $query->orderBy('date', 'desc')->get();

        return $this->respondWithSuccess($exams, 'Exams retrieved successfully.');
    }

    /**
     * Create an exam / assessment.
     */
    public function storeExam(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->isSchoolAdmin() && ! $user->isSuperAdmin() && ! $user->isTeacher()) {
            return ApiResponse::error('Unauthorized to create exams.', 'FORBIDDEN', Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'term_id' => ['required', 'integer', 'exists:terms,id'],
            'grade_level_id' => ['required', 'integer', 'exists:grade_levels,id'],
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', 'string', 'in:quiz,midterm,final,cat,assignment'],
            'weight' => ['required', 'numeric', 'min:0', 'max:100'],
            'max_marks' => ['required', 'numeric', 'min:1'],
            'date' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'in:scheduled,active,completed,published'],
        ]);

        $exam = Exam::create(array_merge($validated, [
            'school_id' => $user->school_id,
        ]));

        return $this->respondWithSuccess($exam->load(['term', 'academicYear', 'gradeLevel']), 'Exam created successfully.', Response::HTTP_CREATED);
    }

    /**
     * List configured grading scales for this school.
     */
    public function indexGradingScales(Request $request): JsonResponse
    {
        $scales = GradingScale::orderByDesc('is_default')->get();
        return $this->respondWithSuccess($scales, 'Grading scales retrieved successfully.');
    }

    /**
     * Store or update a grading scale configuration.
     */
    public function storeGradingScale(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->isSchoolAdmin() && ! $user->isSuperAdmin()) {
            return ApiResponse::error('Only administrators can configure grading scales.', 'FORBIDDEN', Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'scale_type' => ['required', 'string', 'in:letter,gpa,percentage'],
            'is_default' => ['nullable', 'boolean'],
            'rules' => ['required', 'array', 'min:1'],
            'rules.*.min_score' => ['required', 'numeric', 'min:0'],
            'rules.*.max_score' => ['required', 'numeric', 'max:100'],
            'rules.*.grade' => ['required', 'string', 'max:10'],
            'rules.*.gpa_point' => ['nullable', 'numeric', 'min:0', 'max:5'],
            'rules.*.description' => ['nullable', 'string', 'max:50'],
        ]);

        if (! empty($validated['is_default'])) {
            GradingScale::where('school_id', $user->school_id)->update(['is_default' => false]);
        }

        $scale = GradingScale::create(array_merge($validated, [
            'school_id' => $user->school_id,
        ]));

        return $this->respondWithSuccess($scale, 'Grading scale configured successfully.', Response::HTTP_CREATED);
    }

    /**
     * Get section grading roster for a given exam & subject.
     */
    public function getSectionSubjectGrades(
        Request $request,
        Section $section,
        Subject $subject
    ): JsonResponse {
        $user = $request->user();
        if (! $user->isSchoolAdmin() && ! $user->isSuperAdmin() && ! $section->canTeacherGrade($user, $subject->course_id)) {
            return ApiResponse::error('You do not have permission to view or enter grades for this section/subject.', 'FORBIDDEN_GRADING', Response::HTTP_FORBIDDEN);
        }

        $examId = $request->query('exam_id');
        $exam = $examId ? Exam::find($examId) : null;

        // Fetch students in section
        $students = Student::withoutGlobalScopes()
            ->where('school_id', $section->school_id)
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
            ->with('user:id,name,email')
            ->get()
            ->unique('id');

        $gradesQuery = Grade::withoutGlobalScopes()
            ->where('school_id', $section->school_id)
            ->where('subject_id', $subject->id)
            ->whereIn('student_id', $students->pluck('id'));

        if ($examId) {
            $gradesQuery->where('exam_id', $examId);
        }

        $existingGrades = $gradesQuery->get()->keyBy('student_id');

        $roster = $students->map(function ($student) use ($existingGrades, $exam) {
            $grade = $existingGrades->get($student->id);
            return [
                'student_id' => $student->id,
                'name' => $student->user?->name,
                'admission_number' => $student->admission_number,
                'marks_obtained' => $grade?->marks_obtained,
                'max_marks' => $grade?->max_marks ?? ($exam?->max_marks ?? 100),
                'percentage' => $grade?->percentage,
                'remarks' => $grade?->remarks,
                'is_entered' => $grade !== null,
            ];
        });

        return $this->respondWithSuccess([
            'section' => [
                'id' => $section->id,
                'name' => $section->name,
                'grade_level' => $section->gradeLevel?->name,
            ],
            'subject' => [
                'id' => $subject->id,
                'name' => $subject->name,
                'code' => $subject->code,
            ],
            'exam' => $exam ? [
                'id' => $exam->id,
                'name' => $exam->name,
                'type' => $exam->type,
                'max_marks' => $exam->max_marks,
                'weight' => $exam->weight,
            ] : null,
            'roster' => $roster,
        ], 'Grading roster retrieved successfully.');
    }

    /**
     * Enter or batch update grades for an exam and subject.
     * Acceptance criterion:
     * Teacher enters marks for their assigned subject/section; report card auto-aggregates
     * across all subjects for that student once all teachers have submitted.
     */
    public function recordGrades(
        StoreGradeRequest $request,
        ReportCardService $reportCardService
    ): JsonResponse {
        $user = $request->user();
        $exam = Exam::findOrFail($request->input('exam_id'));
        $subject = Subject::findOrFail($request->input('subject_id'));
        $sectionId = $request->input('section_id');
        $section = $sectionId ? Section::find($sectionId) : null;

        // Authorization check: Admin, or teacher assigned to this section/subject
        if (! $user->isSchoolAdmin() && ! $user->isSuperAdmin()) {
            if ($section && ! $section->canTeacherGrade($user, $subject->course_id)) {
                return ApiResponse::error(
                    'You are not authorized to enter grades for this subject in this section.',
                    'FORBIDDEN_GRADING_ENTRY',
                    Response::HTTP_FORBIDDEN
                );
            }
        }

        $result = $reportCardService->recordGrades(
            exam: $exam,
            subject: $subject,
            gradesData: $request->input('grades', []),
            marker: $user,
            section: $section
        );

        return $this->respondWithSuccess(
            $result,
            'Grades recorded and report cards updated successfully.',
            Response::HTTP_OK
        );
    }

    /**
     * Delete an exam / assessment.
     */
    public function destroyExam(Request $request, Exam $exam): JsonResponse
    {
        $user = $request->user();
        if (! $user->isSchoolAdmin() && ! $user->isSuperAdmin()) {
            return ApiResponse::error('Only administrators can delete exams.', 'FORBIDDEN', Response::HTTP_FORBIDDEN);
        }

        $exam->delete();

        return $this->respondWithSuccess(null, 'Assessment removed successfully.');
    }

    /**
     * Delete a grading scale.
     */
    public function destroyGradingScale(Request $request, GradingScale $gradingScale): JsonResponse
    {
        $user = $request->user();
        if (! $user->isSchoolAdmin() && ! $user->isSuperAdmin()) {
            return ApiResponse::error('Only administrators can delete grading scales.', 'FORBIDDEN', Response::HTTP_FORBIDDEN);
        }

        if ($gradingScale->is_default) {
            return ApiResponse::error('Cannot delete the default grading scale.', 'BAD_REQUEST', Response::HTTP_BAD_REQUEST);
        }

        $gradingScale->delete();

        return $this->respondWithSuccess(null, 'Grading scale removed successfully.');
    }
}
