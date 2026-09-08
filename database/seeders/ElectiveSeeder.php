<?php

namespace Database\Seeders;

use App\Enums\DayOfWeek;
use App\Models\AcademicYear;
use App\Models\GradeLevel;
use App\Models\School;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentSubjectSelection;
use App\Models\Subject;
use App\Models\SubjectOffering;
use App\Models\TimetableSlot;
use App\Models\User;
use App\Tenancy\TenantManager;
use Illuminate\Database\Seeder;

class ElectiveSeeder extends Seeder
{
    public function run(): void
    {
        $tenantManager = app(TenantManager::class);

        $greenwood = School::where('subdomain', 'greenwood')->first();
        if (! $greenwood) {
            return;
        }

        $tenantManager->setTenant($greenwood);

        $academicYear = AcademicYear::withoutGlobalScopes()
            ->where('school_id', $greenwood->id)
            ->where('is_active', true)
            ->first();

        if (! $academicYear) {
            return;
        }

        // Find Grade 10 (or Grade 9 if that's the primary high school grade)
        $gradeLevel = GradeLevel::withoutGlobalScopes()
            ->where('school_id', $greenwood->id)
            ->where('code', 'G10')
            ->first()
            ?? GradeLevel::withoutGlobalScopes()
                ->where('school_id', $greenwood->id)
                ->where('code', 'G9')
                ->first();

        if (! $gradeLevel) {
            return;
        }

        $admin = User::where('school_id', $greenwood->id)->where('email', 'admin@greenwood.edu')->first()
            ?? User::where('school_id', $greenwood->id)->first();

        // 1. Create 3 Elective Subjects
        $csSubject = Subject::updateOrCreate(
            ['school_id' => $greenwood->id, 'grade_level_id' => $gradeLevel->id, 'code' => 'CS-101'],
            [
                'name' => 'Computer Science & Programming',
                'is_elective' => true,
                'credit_hours' => 1.0,
                'description' => 'Introduction to algorithms, logic, and software development.',
            ]
        );

        $artSubject = Subject::updateOrCreate(
            ['school_id' => $greenwood->id, 'grade_level_id' => $gradeLevel->id, 'code' => 'ART-101'],
            [
                'name' => 'Studio Art & Design',
                'is_elective' => true,
                'credit_hours' => 1.0,
                'description' => 'Visual expression, drawing techniques, and art appreciation.',
            ]
        );

        $musSubject = Subject::updateOrCreate(
            ['school_id' => $greenwood->id, 'grade_level_id' => $gradeLevel->id, 'code' => 'MUS-101'],
            [
                'name' => 'Music Theory & Ensemble',
                'is_elective' => true,
                'credit_hours' => 1.0,
                'description' => 'Harmony, ear training, and collaborative performance.',
            ]
        );

        // 2. Configure Subject Offerings with Capacity and Open Enrollment Window
        $csOffering = SubjectOffering::updateOrCreate(
            [
                'school_id' => $greenwood->id,
                'academic_year_id' => $academicYear->id,
                'grade_level_id' => $gradeLevel->id,
                'subject_id' => $csSubject->id,
            ],
            [
                'type' => 'elective',
                'max_students' => 20,
                'enrollment_start' => now()->subDays(10),
                'enrollment_end' => now()->addDays(20),
                'is_open' => true,
            ]
        );

        $artOffering = SubjectOffering::updateOrCreate(
            [
                'school_id' => $greenwood->id,
                'academic_year_id' => $academicYear->id,
                'grade_level_id' => $gradeLevel->id,
                'subject_id' => $artSubject->id,
            ],
            [
                'type' => 'elective',
                'max_students' => 15,
                'enrollment_start' => now()->subDays(10),
                'enrollment_end' => now()->addDays(20),
                'is_open' => true,
            ]
        );

        $musOffering = SubjectOffering::updateOrCreate(
            [
                'school_id' => $greenwood->id,
                'academic_year_id' => $academicYear->id,
                'grade_level_id' => $gradeLevel->id,
                'subject_id' => $musSubject->id,
            ],
            [
                'type' => 'elective',
                'max_students' => 15,
                'enrollment_start' => now()->subDays(10),
                'enrollment_end' => now()->addDays(20),
                'is_open' => true,
            ]
        );

        // 3. Enroll sample students
        $bartUser = User::where('email', 'bart.simpson@greenwood.edu')->first()
            ?? User::where('email', 'student@greenwood.edu')->first();
        $lisaUser = User::where('email', 'lisa.simpson@greenwood.edu')->first();

        if ($bartUser && $bartUser->student) {
            StudentSubjectSelection::updateOrCreate(
                [
                    'school_id' => $greenwood->id,
                    'student_id' => $bartUser->student->id,
                    'academic_year_id' => $academicYear->id,
                    'subject_id' => $csSubject->id,
                ],
                [
                    'status' => 'enrolled',
                    'selected_at' => now()->subDays(2),
                    'selected_by' => $bartUser->id,
                ]
            );
        }

        if ($lisaUser && $lisaUser->student) {
            StudentSubjectSelection::updateOrCreate(
                [
                    'school_id' => $greenwood->id,
                    'student_id' => $lisaUser->student->id,
                    'academic_year_id' => $academicYear->id,
                    'subject_id' => $musSubject->id,
                ],
                [
                    'status' => 'enrolled',
                    'selected_at' => now()->subDays(2),
                    'selected_by' => $lisaUser->id,
                ]
            );
        }

        // 4. Timetable slots for electives
        $sectionA = Section::where('school_id', $greenwood->id)->where('grade_level_id', $gradeLevel->id)->first();
        $sectionB = Section::where('school_id', $greenwood->id)->where('grade_level_id', $gradeLevel->id)->skip(1)->first();

        $teacherEdna = User::where('email', 'teacher@greenwood.edu')->first();
        $teacherHoover = User::where('email', 'hoover@greenwood.edu')->first();

        if ($sectionA) {
            TimetableSlot::updateOrCreate(
                [
                    'school_id' => $greenwood->id,
                    'academic_year_id' => $academicYear->id,
                    'section_id' => $sectionA->id,
                    'day_of_week' => DayOfWeek::WEDNESDAY->value,
                    'period_number' => 5,
                ],
                [
                    'subject_id' => $csSubject->id,
                    'teacher_id' => $teacherEdna?->id,
                    'start_time' => '12:30',
                    'end_time' => '13:20',
                    'room' => 'Computer Lab',
                    'color' => '#8b5cf6',
                ]
            );
        }

        if ($sectionB) {
            TimetableSlot::updateOrCreate(
                [
                    'school_id' => $greenwood->id,
                    'academic_year_id' => $academicYear->id,
                    'section_id' => $sectionB->id,
                    'day_of_week' => DayOfWeek::WEDNESDAY->value,
                    'period_number' => 5,
                ],
                [
                    'subject_id' => $musSubject->id,
                    'teacher_id' => $teacherHoover?->id,
                    'start_time' => '12:30',
                    'end_time' => '13:20',
                    'room' => 'Music Hall',
                    'color' => '#ec4899',
                ]
            );
        }
    }
}
