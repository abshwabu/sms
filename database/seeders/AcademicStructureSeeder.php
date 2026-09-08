<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\GradeLevel;
use App\Models\School;
use App\Models\Section;
use App\Models\StudentSectionAssignment;
use App\Models\Term;
use App\Models\User;
use App\Tenancy\TenantManager;
use Illuminate\Database\Seeder;

class AcademicStructureSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tenantManager = app(TenantManager::class);

        // 1. Greenwood High Academic Setup
        $greenwood = School::where('subdomain', 'greenwood')->firstOrFail();
        $tenantManager->setTenant($greenwood);

        $edna = User::where('email', 'teacher@greenwood.edu')->first();
        $bart = User::where('email', 'student@greenwood.edu')->first();

        // Grade levels for Greenwood
        $g9 = GradeLevel::updateOrCreate(['school_id' => $greenwood->id, 'code' => 'G9'], [
            'name' => 'Grade 9 (Freshman)',
            'sequence' => 1,
            'description' => 'First year of secondary high school education.',
        ]);

        $g10 = GradeLevel::updateOrCreate(['school_id' => $greenwood->id, 'code' => 'G10'], [
            'name' => 'Grade 10 (Sophomore)',
            'sequence' => 2,
            'description' => 'Second year of secondary high school education.',
        ]);

        $g11 = GradeLevel::updateOrCreate(['school_id' => $greenwood->id, 'code' => 'G11'], [
            'name' => 'Grade 11 (Junior)',
            'sequence' => 3,
            'description' => 'Pre-graduation advanced preparatory year.',
        ]);

        $g12 = GradeLevel::updateOrCreate(['school_id' => $greenwood->id, 'code' => 'G12'], [
            'name' => 'Grade 12 (Senior)',
            'sequence' => 4,
            'description' => 'Graduating class of high school.',
        ]);

        // Historical Year (2024/2025) - CLOSED
        $year2024 = AcademicYear::updateOrCreate(['school_id' => $greenwood->id, 'name' => '2024/2025'], [
            'start_date' => '2024-09-01',
            'end_date' => '2025-06-30',
            'is_active' => false,
            'is_closed' => false, // create as open first so terms and sections can be attached
        ]);

        Term::updateOrCreate(['academic_year_id' => $year2024->id, 'name' => 'Term 1'], [
            'school_id' => $greenwood->id,
            'start_date' => '2024-09-01',
            'end_date' => '2025-01-15',
            'is_active' => false,
        ]);

        Term::updateOrCreate(['academic_year_id' => $year2024->id, 'name' => 'Term 2'], [
            'school_id' => $greenwood->id,
            'start_date' => '2025-01-16',
            'end_date' => '2025-06-30',
            'is_active' => false,
        ]);

        $histSectionG9 = Section::updateOrCreate([
            'academic_year_id' => $year2024->id,
            'grade_level_id' => $g9->id,
            'name' => 'Section A',
        ], [
            'school_id' => $greenwood->id,
            'capacity' => 30,
            'homeroom_teacher_id' => $edna?->id,
        ]);

        if ($bart) {
            StudentSectionAssignment::updateOrCreate([
                'academic_year_id' => $year2024->id,
                'student_id' => $bart->id,
            ], [
                'school_id' => $greenwood->id,
                'section_id' => $histSectionG9->id,
                'roll_number' => 'G9-001',
                'status' => 'promoted',
                'enrolled_at' => '2024-09-01',
            ]);
        }

        // Close historical year
        $year2024->update(['is_closed' => true, 'is_active' => false]);

        // Current Active Year (2025/2026)
        $year2025 = AcademicYear::updateOrCreate(['school_id' => $greenwood->id, 'name' => '2025/2026'], [
            'start_date' => '2025-09-01',
            'end_date' => '2026-06-30',
            'is_active' => true,
            'is_closed' => false,
        ]);

        Term::updateOrCreate(['academic_year_id' => $year2025->id, 'name' => 'Fall Semester'], [
            'school_id' => $greenwood->id,
            'start_date' => '2025-09-01',
            'end_date' => '2026-01-15',
            'is_active' => true,
        ]);

        Term::updateOrCreate(['academic_year_id' => $year2025->id, 'name' => 'Spring Semester'], [
            'school_id' => $greenwood->id,
            'start_date' => '2026-01-16',
            'end_date' => '2026-06-30',
            'is_active' => false,
        ]);

        $activeSectionG10 = Section::updateOrCreate([
            'academic_year_id' => $year2025->id,
            'grade_level_id' => $g10->id,
            'name' => 'Section A',
        ], [
            'school_id' => $greenwood->id,
            'capacity' => 28,
            'homeroom_teacher_id' => $edna?->id,
        ]);

        if ($bart) {
            // Promoted to Grade 10 Section A for 2025/2026!
            StudentSectionAssignment::updateOrCreate([
                'academic_year_id' => $year2025->id,
                'student_id' => $bart->id,
            ], [
                'school_id' => $greenwood->id,
                'section_id' => $activeSectionG10->id,
                'roll_number' => 'G10-001',
                'status' => 'enrolled',
                'enrolled_at' => '2025-09-01',
            ]);
        }


        // 2. Oakridge Academy Academic Setup
        $oakridge = School::where('subdomain', 'oakridge')->firstOrFail();
        $tenantManager->setTenant($oakridge);

        $mcgonagall = User::where('email', 'teacher@oakridge.edu')->first();
        $harry = User::where('email', 'student@oakridge.edu')->first();

        $oakG11 = GradeLevel::updateOrCreate(['school_id' => $oakridge->id, 'code' => 'G11'], [
            'name' => 'Grade 11 (Sixth Form)',
            'sequence' => 1,
            'description' => 'Advanced levels and university preparation.',
        ]);

        $oakYear2025 = AcademicYear::updateOrCreate(['school_id' => $oakridge->id, 'name' => '2025/2026'], [
            'start_date' => '2025-09-01',
            'end_date' => '2026-06-30',
            'is_active' => true,
            'is_closed' => false,
        ]);

        Term::updateOrCreate(['academic_year_id' => $oakYear2025->id, 'name' => 'Autumn Term'], [
            'school_id' => $oakridge->id,
            'start_date' => '2025-09-01',
            'end_date' => '2025-12-20',
            'is_active' => true,
        ]);

        Term::updateOrCreate(['academic_year_id' => $oakYear2025->id, 'name' => 'Winter Term'], [
            'school_id' => $oakridge->id,
            'start_date' => '2026-01-05',
            'end_date' => '2026-04-10',
            'is_active' => false,
        ]);

        $oakSection = Section::updateOrCreate([
            'academic_year_id' => $oakYear2025->id,
            'grade_level_id' => $oakG11->id,
            'name' => 'Gryffindor Cohort',
        ], [
            'school_id' => $oakridge->id,
            'capacity' => 25,
            'homeroom_teacher_id' => $mcgonagall?->id,
        ]);

        if ($harry) {
            StudentSectionAssignment::updateOrCreate([
                'academic_year_id' => $oakYear2025->id,
                'student_id' => $harry->id,
            ], [
                'school_id' => $oakridge->id,
                'section_id' => $oakSection->id,
                'roll_number' => 'OAK-101',
                'status' => 'enrolled',
                'enrolled_at' => '2025-09-01',
            ]);
        }

        // 3. Maplewood Elementary Academic Setup (Elementary Grades K-5)
        $maplewood = School::where('subdomain', 'maplewood')->first();
        if ($maplewood) {
            $tenantManager->setTenant($maplewood);

            $claraTeacher = User::where('email', 'teacher@maplewood.edu')->first();
            $tommyStudent = User::where('email', 'student@maplewood.edu')->first();

            // Elementary Grade Levels
            $elemGrades = [
                ['code' => 'KG', 'name' => 'Kindergarten', 'sequence' => 0, 'description' => 'Early childhood foundation year.'],
                ['code' => 'G1', 'name' => 'Grade 1', 'sequence' => 1, 'description' => 'First grade primary education.'],
                ['code' => 'G2', 'name' => 'Grade 2', 'sequence' => 2, 'description' => 'Second grade primary education.'],
                ['code' => 'G3', 'name' => 'Grade 3', 'sequence' => 3, 'description' => 'Third grade intermediate primary education.'],
                ['code' => 'G4', 'name' => 'Grade 4', 'sequence' => 4, 'description' => 'Fourth grade upper elementary education.'],
                ['code' => 'G5', 'name' => 'Grade 5', 'sequence' => 5, 'description' => 'Fifth grade elementary graduating class.'],
            ];

            $gradeModels = [];
            foreach ($elemGrades as $gData) {
                $gradeModels[$gData['code']] = GradeLevel::updateOrCreate(
                    ['school_id' => $maplewood->id, 'code' => $gData['code']],
                    $gData
                );
            }

            // Academic Year 2025/2026
            $mapleYear = AcademicYear::updateOrCreate(
                ['school_id' => $maplewood->id, 'name' => '2025/2026'],
                [
                    'start_date' => '2025-09-01',
                    'end_date' => '2026-06-30',
                    'is_active' => true,
                    'is_closed' => false,
                ]
            );

            Term::updateOrCreate(['academic_year_id' => $mapleYear->id, 'name' => 'Fall Term'], [
                'school_id' => $maplewood->id,
                'start_date' => '2025-09-01',
                'end_date' => '2025-12-19',
                'is_active' => true,
            ]);

            Term::updateOrCreate(['academic_year_id' => $mapleYear->id, 'name' => 'Winter Term'], [
                'school_id' => $maplewood->id,
                'start_date' => '2026-01-05',
                'end_date' => '2026-03-27',
                'is_active' => false,
            ]);

            Term::updateOrCreate(['academic_year_id' => $mapleYear->id, 'name' => 'Spring Term'], [
                'school_id' => $maplewood->id,
                'start_date' => '2026-04-06',
                'end_date' => '2026-06-25',
                'is_active' => false,
            ]);

            // Elementary Sections across K-5
            $sectionsData = [
                ['grade' => 'KG', 'name' => 'Room K1', 'capacity' => 20, 'teacher' => null],
                ['grade' => 'G1', 'name' => 'Room 101', 'capacity' => 22, 'teacher' => null],
                ['grade' => 'G2', 'name' => 'Room 102', 'capacity' => 24, 'teacher' => null],
                ['grade' => 'G3', 'name' => 'Room 103', 'capacity' => 24, 'teacher' => $claraTeacher?->id],
                ['grade' => 'G4', 'name' => 'Room 104', 'capacity' => 26, 'teacher' => null],
                ['grade' => 'G5', 'name' => 'Room 105', 'capacity' => 26, 'teacher' => null],
            ];

            $mapleSections = [];
            foreach ($sectionsData as $sData) {
                $gModel = $gradeModels[$sData['grade']];
                $sec = Section::updateOrCreate(
                    [
                        'academic_year_id' => $mapleYear->id,
                        'grade_level_id' => $gModel->id,
                        'name' => $sData['name'],
                    ],
                    [
                        'school_id' => $maplewood->id,
                        'capacity' => $sData['capacity'],
                        'homeroom_teacher_id' => $sData['teacher'],
                    ]
                );
                $mapleSections[$sData['grade']] = $sec;
            }

            // Assign Tommy Vance to Grade 3 - Room 103
            if ($tommyStudent && isset($mapleSections['G3'])) {
                StudentSectionAssignment::updateOrCreate([
                    'academic_year_id' => $mapleYear->id,
                    'student_id' => $tommyStudent->id,
                ], [
                    'school_id' => $maplewood->id,
                    'section_id' => $mapleSections['G3']->id,
                    'roll_number' => 'MAP-001',
                    'status' => 'enrolled',
                    'enrolled_at' => '2025-09-01',
                ]);
            }
        }

        $tenantManager->clearTenant();
    }
}
