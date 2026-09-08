<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\Exam;
use App\Models\Grade;
use App\Models\GradeLevel;
use App\Models\GradingScale;
use App\Models\ReportCard;
use App\Models\School;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use App\Services\ReportCardService;
use App\Tenancy\TenantManager;
use Illuminate\Database\Seeder;

class GradingSeeder extends Seeder
{
    public function run(): void
    {
        $tenantManager = app(TenantManager::class);
        $reportCardService = app(ReportCardService::class);

        $greenwood = School::where('subdomain', 'greenwood')->first();
        if (! $greenwood) {
            return;
        }

        $tenantManager->setTenant($greenwood);

        // 1. Grading Scale
        $scale = GradingScale::updateOrCreate(
            ['school_id' => $greenwood->id, 'name' => 'Standard Letter (A–F)'],
            [
                'scale_type' => 'letter',
                'is_default' => true,
                'rules' => [
                    ['min_score' => 90, 'max_score' => 100, 'grade' => 'A', 'gpa_point' => 4.0, 'description' => 'Excellent'],
                    ['min_score' => 80, 'max_score' => 89.99, 'grade' => 'B', 'gpa_point' => 3.0, 'description' => 'Good'],
                    ['min_score' => 70, 'max_score' => 79.99, 'grade' => 'C', 'gpa_point' => 2.0, 'description' => 'Satisfactory'],
                    ['min_score' => 60, 'max_score' => 69.99, 'grade' => 'D', 'gpa_point' => 1.0, 'description' => 'Pass'],
                    ['min_score' => 0, 'max_score' => 59.99, 'grade' => 'F', 'gpa_point' => 0.0, 'description' => 'Fail'],
                ],
            ]
        );

        $activeYear = AcademicYear::where('school_id', $greenwood->id)->where('name', '2025/2026')->first();
        if (! $activeYear) {
            return;
        }

        $fallTerm = Term::where('school_id', $greenwood->id)->where('academic_year_id', $activeYear->id)->where('name', 'Fall Semester')->first();
        if (! $fallTerm) {
            $fallTerm = Term::where('school_id', $greenwood->id)->where('academic_year_id', $activeYear->id)->first();
        }

        $grade9 = GradeLevel::where('school_id', $greenwood->id)->where('name', 'Grade 9')->first();
        if (! $grade9) {
            $grade9 = GradeLevel::where('school_id', $greenwood->id)->first();
        }

        $sectionA = Section::where('school_id', $greenwood->id)->where('grade_level_id', $grade9->id)->first();
        $teacher = User::where('email', 'teacher@greenwood.edu')->first();

        // 2. Subjects for Grade 9
        $mathCourse = Course::where('school_id', $greenwood->id)->where('code', 'MTH-01')->first();
        $engCourse = Course::where('school_id', $greenwood->id)->where('code', 'ENG-01')->first();
        $sciCourse = Course::where('school_id', $greenwood->id)->where('code', 'SCI-01')->first();

        $subMath = Subject::updateOrCreate(
            ['school_id' => $greenwood->id, 'grade_level_id' => $grade9->id, 'code' => 'MTH-901'],
            [
                'name' => 'Mathematics 9',
                'course_id' => $mathCourse?->id,
                'credit_hours' => 1.0,
                'description' => 'Algebra and Geometry foundations',
            ]
        );

        $subEng = Subject::updateOrCreate(
            ['school_id' => $greenwood->id, 'grade_level_id' => $grade9->id, 'code' => 'ENG-901'],
            [
                'name' => 'English Literature 9',
                'course_id' => $engCourse?->id,
                'credit_hours' => 1.0,
                'description' => 'Composition and Literature',
            ]
        );

        $subSci = Subject::updateOrCreate(
            ['school_id' => $greenwood->id, 'grade_level_id' => $grade9->id, 'code' => 'SCI-901'],
            [
                'name' => 'Integrated Science',
                'course_id' => $sciCourse?->id,
                'credit_hours' => 1.0,
                'description' => 'Physical and Life Sciences',
            ]
        );

        // 3. Exams for Fall Term Grade 9
        $midterm = Exam::updateOrCreate(
            ['school_id' => $greenwood->id, 'term_id' => $fallTerm->id, 'grade_level_id' => $grade9->id, 'name' => 'Midterm Assessment'],
            [
                'academic_year_id' => $activeYear->id,
                'type' => 'midterm',
                'weight' => 40.00,
                'max_marks' => 100.00,
                'date' => '2025-10-20',
                'status' => 'completed',
            ]
        );

        $final = Exam::updateOrCreate(
            ['school_id' => $greenwood->id, 'term_id' => $fallTerm->id, 'grade_level_id' => $grade9->id, 'name' => 'Final Examination'],
            [
                'academic_year_id' => $activeYear->id,
                'type' => 'final',
                'weight' => 60.00,
                'max_marks' => 100.00,
                'date' => '2025-12-15',
                'status' => 'completed',
            ]
        );

        // 4. Sample Students Grades (Bart & Lisa)
        $bart = Student::where('school_id', $greenwood->id)->where('admission_number', 'GRE-25-00101')->first();
        $lisa = Student::where('school_id', $greenwood->id)->where('admission_number', 'GRE-25-00102')->first();

        if ($lisa) {
            // Lisa's grades (Math: 96 & 98, Eng: 94 & 95, Sci: 98 & 99)
            $lisaGrades = [
                [$subMath->id, $midterm->id, 96.0],
                [$subMath->id, $final->id, 98.0],
                [$subEng->id, $midterm->id, 94.0],
                [$subEng->id, $final->id, 95.0],
                [$subSci->id, $midterm->id, 98.0],
                [$subSci->id, $final->id, 99.0],
            ];

            foreach ($lisaGrades as [$subId, $exId, $marks]) {
                Grade::updateOrCreate(
                    ['school_id' => $greenwood->id, 'student_id' => $lisa->id, 'subject_id' => $subId, 'exam_id' => $exId],
                    ['section_id' => $sectionA?->id, 'marks_obtained' => $marks, 'max_marks' => 100.00, 'entered_by' => $teacher?->id]
                );
            }

            $lisaRc = $reportCardService->aggregateStudentReportCard($lisa, $fallTerm, $scale, $teacher);
            $lisaRc->update([
                'homeroom_remarks' => 'Outstanding academic achievement across all disciplines.',
                'principal_remarks' => 'Honor roll student with exemplary conduct.',
                'status' => 'published',
                'published_at' => now(),
            ]);
        }

        if ($bart) {
            // Bart's grades (Math: 68 & 72, Eng: 70 & 75, Sci: 72 & 70)
            $bartGrades = [
                [$subMath->id, $midterm->id, 68.0],
                [$subMath->id, $final->id, 72.0],
                [$subEng->id, $midterm->id, 70.0],
                [$subEng->id, $final->id, 75.0],
                [$subSci->id, $midterm->id, 72.0],
                [$subSci->id, $final->id, 70.0],
            ];

            foreach ($bartGrades as [$subId, $exId, $marks]) {
                Grade::updateOrCreate(
                    ['school_id' => $greenwood->id, 'student_id' => $bart->id, 'subject_id' => $subId, 'exam_id' => $exId],
                    ['section_id' => $sectionA?->id, 'marks_obtained' => $marks, 'max_marks' => 100.00, 'entered_by' => $teacher?->id]
                );
            }

            $bartRc = $reportCardService->aggregateStudentReportCard($bart, $fallTerm, $scale, $teacher);
            $bartRc->update([
                'homeroom_remarks' => 'Shows good potential, needs to focus more during homework assignments.',
                'status' => 'draft', // Left as draft to test parent permission enforcement
            ]);
        }

        if ($sectionA && $fallTerm) {
            $reportCardService->updateSectionRanks($sectionA, $fallTerm);
        }
    }
}
