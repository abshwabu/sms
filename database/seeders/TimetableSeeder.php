<?php

namespace Database\Seeders;

use App\Enums\DayOfWeek;
use App\Models\AcademicYear;
use App\Models\GradeLevel;
use App\Models\School;
use App\Models\Section;
use App\Models\Subject;
use App\Models\TimetableSlot;
use App\Models\User;
use App\Services\TimetableService;
use App\Tenancy\TenantManager;
use Illuminate\Database\Seeder;

class TimetableSeeder extends Seeder
{
    public function run(): void
    {
        $tenantManager = app(TenantManager::class);

        $greenwood = School::where('subdomain', 'greenwood')->first();
        if (! $greenwood) {
            return;
        }

        $tenantManager->setTenant($greenwood);

        $activeYear = AcademicYear::where('school_id', $greenwood->id)
            ->where('name', '2025/2026')
            ->first();

        if (! $activeYear) {
            return;
        }

        $g9 = GradeLevel::where('school_id', $greenwood->id)->where('name', 'like', '%Grade 9%')->first()
            ?? GradeLevel::where('school_id', $greenwood->id)->first();

        $edna = User::where('email', 'teacher@greenwood.edu')->first();
        $hoover = User::where('email', 'hoover@greenwood.edu')->first();

        // 1. Section A
        $sectionA = Section::where('school_id', $greenwood->id)
            ->where('academic_year_id', $activeYear->id)
            ->where('name', 'Section A')
            ->first();

        // 2. Ensure Section B exists in Greenwood for 2025/2026
        $sectionB = Section::updateOrCreate(
            [
                'school_id' => $greenwood->id,
                'academic_year_id' => $activeYear->id,
                'name' => 'Section B',
            ],
            [
                'grade_level_id' => $g9?->id,
                'capacity' => 28,
                'homeroom_teacher_id' => $hoover?->id,
            ]
        );

        // Fetch Grade 9 Subjects
        $subMath = Subject::where('school_id', $greenwood->id)->where('code', 'MTH-901')->first();
        $subEng = Subject::where('school_id', $greenwood->id)->where('code', 'ENG-901')->first();
        $subSci = Subject::where('school_id', $greenwood->id)->where('code', 'SCI-901')->first();

        if (! $subMath || ! $subEng || ! $subSci || ! $sectionA) {
            return;
        }

        // 3. Weekly Slots for Section A
        $sectionASlots = [
            // Monday
            ['day' => DayOfWeek::MONDAY->value, 'period' => 1, 'subject' => $subEng->id, 'teacher' => $edna?->id, 'room' => 'Room 101', 'color' => '#6366f1'],
            ['day' => DayOfWeek::MONDAY->value, 'period' => 2, 'subject' => $subMath->id, 'teacher' => $hoover?->id, 'room' => 'Room 101', 'color' => '#0ea5e9'],
            ['day' => DayOfWeek::MONDAY->value, 'period' => 3, 'subject' => $subSci->id, 'teacher' => $edna?->id, 'room' => 'Lab 1', 'color' => '#10b981'],
            ['day' => DayOfWeek::MONDAY->value, 'period' => 4, 'subject' => $subMath->id, 'teacher' => $hoover?->id, 'room' => 'Room 101', 'color' => '#0ea5e9'],

            // Tuesday
            ['day' => DayOfWeek::TUESDAY->value, 'period' => 1, 'subject' => $subMath->id, 'teacher' => $hoover?->id, 'room' => 'Room 101', 'color' => '#0ea5e9'],
            ['day' => DayOfWeek::TUESDAY->value, 'period' => 2, 'subject' => $subEng->id, 'teacher' => $edna?->id, 'room' => 'Room 101', 'color' => '#6366f1'],
            ['day' => DayOfWeek::TUESDAY->value, 'period' => 3, 'subject' => $subSci->id, 'teacher' => $edna?->id, 'room' => 'Lab 1', 'color' => '#10b981'],

            // Wednesday
            ['day' => DayOfWeek::WEDNESDAY->value, 'period' => 1, 'subject' => $subSci->id, 'teacher' => $edna?->id, 'room' => 'Lab 1', 'color' => '#10b981'],
            ['day' => DayOfWeek::WEDNESDAY->value, 'period' => 2, 'subject' => $subMath->id, 'teacher' => $hoover?->id, 'room' => 'Room 101', 'color' => '#0ea5e9'],
            ['day' => DayOfWeek::WEDNESDAY->value, 'period' => 3, 'subject' => $subEng->id, 'teacher' => $edna?->id, 'room' => 'Room 101', 'color' => '#6366f1'],

            // Thursday
            ['day' => DayOfWeek::THURSDAY->value, 'period' => 1, 'subject' => $subEng->id, 'teacher' => $edna?->id, 'room' => 'Room 101', 'color' => '#6366f1'],
            ['day' => DayOfWeek::THURSDAY->value, 'period' => 2, 'subject' => $subSci->id, 'teacher' => $edna?->id, 'room' => 'Lab 1', 'color' => '#10b981'],

            // Friday
            ['day' => DayOfWeek::FRIDAY->value, 'period' => 1, 'subject' => $subMath->id, 'teacher' => $hoover?->id, 'room' => 'Room 101', 'color' => '#0ea5e9'],
            ['day' => DayOfWeek::FRIDAY->value, 'period' => 2, 'subject' => $subEng->id, 'teacher' => $edna?->id, 'room' => 'Room 101', 'color' => '#6366f1'],
        ];

        foreach ($sectionASlots as $s) {
            $times = TimetableService::defaultPeriodTimes($s['period']);
            TimetableSlot::updateOrCreate(
                [
                    'school_id' => $greenwood->id,
                    'academic_year_id' => $activeYear->id,
                    'section_id' => $sectionA->id,
                    'day_of_week' => $s['day'],
                    'period_number' => $s['period'],
                ],
                [
                    'subject_id' => $s['subject'],
                    'teacher_id' => $s['teacher'],
                    'start_time' => $times['start'],
                    'end_time' => $times['end'],
                    'room' => $s['room'],
                    'color' => $s['color'],
                ]
            );
        }

        // 4. Weekly Slots for Section B (Edna teaches English in Section B during Period 4 and 5)
        // Notice: Period 4 on Monday does NOT conflict with Section A (where Edna teaches period 1 & 3)
        $sectionBSlots = [
            ['day' => DayOfWeek::MONDAY->value, 'period' => 1, 'subject' => $subMath->id, 'teacher' => $hoover?->id, 'room' => 'Room 102', 'color' => '#0ea5e9'],
            ['day' => DayOfWeek::MONDAY->value, 'period' => 4, 'subject' => $subEng->id, 'teacher' => $edna?->id, 'room' => 'Room 102', 'color' => '#6366f1'],
            ['day' => DayOfWeek::TUESDAY->value, 'period' => 4, 'subject' => $subEng->id, 'teacher' => $edna?->id, 'room' => 'Room 102', 'color' => '#6366f1'],
            ['day' => DayOfWeek::WEDNESDAY->value, 'period' => 4, 'subject' => $subSci->id, 'teacher' => $edna?->id, 'room' => 'Lab 2', 'color' => '#10b981'],
        ];

        foreach ($sectionBSlots as $s) {
            $times = TimetableService::defaultPeriodTimes($s['period']);
            TimetableSlot::updateOrCreate(
                [
                    'school_id' => $greenwood->id,
                    'academic_year_id' => $activeYear->id,
                    'section_id' => $sectionB->id,
                    'day_of_week' => $s['day'],
                    'period_number' => $s['period'],
                ],
                [
                    'subject_id' => $s['subject'],
                    'teacher_id' => $s['teacher'],
                    'start_time' => $times['start'],
                    'end_time' => $times['end'],
                    'room' => $s['room'],
                    'color' => $s['color'],
                ]
            );
        }

        $tenantManager->clearTenant();
    }
}
