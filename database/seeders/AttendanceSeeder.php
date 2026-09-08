<?php

namespace Database\Seeders;

use App\Enums\AttendanceStatus;
use App\Models\AcademicYear;
use App\Models\AttendanceRecord;
use App\Models\School;
use App\Models\SchoolCalendar;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use App\Tenancy\TenantManager;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class AttendanceSeeder extends Seeder
{
    public function run(): void
    {
        $tenantManager = app(TenantManager::class);

        // 1. Greenwood High School Attendance & Calendar
        $greenwood = School::where('subdomain', 'greenwood')->first();
        if ($greenwood) {
            $tenantManager->setTenant($greenwood);

            $activeYear = AcademicYear::where('school_id', $greenwood->id)
                ->where('name', '2025/2026')
                ->first();

            // Seed School Calendar Holidays
            $holidays = [
                ['date' => '2025-09-01', 'description' => 'Labor Day'],
                ['date' => '2025-11-27', 'description' => 'Thanksgiving Day'],
                ['date' => '2025-11-28', 'description' => 'Day after Thanksgiving'],
                ['date' => '2025-12-25', 'description' => 'Christmas Day'],
                ['date' => '2026-01-01', 'description' => 'New Year Day'],
            ];

            foreach ($holidays as $h) {
                SchoolCalendar::updateOrCreate(
                    ['school_id' => $greenwood->id, 'date' => $h['date']],
                    [
                        'academic_year_id' => $activeYear?->id,
                        'day_type' => 'holiday',
                        'is_school_day' => false,
                        'description' => $h['description'],
                    ]
                );
            }

            // Section A Daily Attendance
            $secA = Section::where('school_id', $greenwood->id)
                ->where('academic_year_id', $activeYear?->id)
                ->first();

            $edna = User::where('email', 'teacher@greenwood.edu')->first();

            $bart = Student::where('school_id', $greenwood->id)->where('admission_number', 'GRE-25-00101')->first();
            $lisa = Student::where('school_id', $greenwood->id)->where('admission_number', 'GRE-25-00102')->first();
            $milhouse = Student::where('school_id', $greenwood->id)->where('admission_number', 'GRE-25-00103')->first();
            $nelson = Student::where('school_id', $greenwood->id)->where('admission_number', 'GRE-25-00104')->first();
            $martin = Student::where('school_id', $greenwood->id)->where('admission_number', 'GRE-25-00105')->first();

            if ($secA && $edna && $bart && $lisa && $milhouse) {
                $days = [
                    '2025-09-02' => [
                        $bart->id => ['status' => AttendanceStatus::PRESENT->value, 'remarks' => null],
                        $lisa->id => ['status' => AttendanceStatus::PRESENT->value, 'remarks' => null],
                        $milhouse->id => ['status' => AttendanceStatus::PRESENT->value, 'remarks' => null],
                        $nelson->id => ['status' => AttendanceStatus::PRESENT->value, 'remarks' => null],
                        $martin->id => ['status' => AttendanceStatus::PRESENT->value, 'remarks' => null],
                    ],
                    '2025-09-03' => [
                        $bart->id => ['status' => AttendanceStatus::LATE->value, 'remarks' => 'School bus delayed'],
                        $lisa->id => ['status' => AttendanceStatus::PRESENT->value, 'remarks' => null],
                        $milhouse->id => ['status' => AttendanceStatus::PRESENT->value, 'remarks' => null],
                        $nelson->id => ['status' => AttendanceStatus::PRESENT->value, 'remarks' => null],
                        $martin->id => ['status' => AttendanceStatus::PRESENT->value, 'remarks' => null],
                    ],
                    '2025-09-04' => [
                        $bart->id => ['status' => AttendanceStatus::ABSENT->value, 'remarks' => 'Unexcused illness'],
                        $lisa->id => ['status' => AttendanceStatus::PRESENT->value, 'remarks' => null],
                        $milhouse->id => ['status' => AttendanceStatus::PRESENT->value, 'remarks' => null],
                        $nelson->id => ['status' => AttendanceStatus::PRESENT->value, 'remarks' => null],
                        $martin->id => ['status' => AttendanceStatus::PRESENT->value, 'remarks' => null],
                    ],
                    '2025-09-05' => [
                        $bart->id => ['status' => AttendanceStatus::EXCUSED->value, 'remarks' => 'Medical checkup with doctor note'],
                        $lisa->id => ['status' => AttendanceStatus::PRESENT->value, 'remarks' => null],
                        $milhouse->id => ['status' => AttendanceStatus::PRESENT->value, 'remarks' => null],
                        $nelson->id => ['status' => AttendanceStatus::PRESENT->value, 'remarks' => null],
                        $martin->id => ['status' => AttendanceStatus::PRESENT->value, 'remarks' => null],
                    ],
                    '2025-09-08' => [
                        $bart->id => ['status' => AttendanceStatus::PRESENT->value, 'remarks' => null],
                        $lisa->id => ['status' => AttendanceStatus::PRESENT->value, 'remarks' => null],
                        $milhouse->id => ['status' => AttendanceStatus::PRESENT->value, 'remarks' => null],
                        $nelson->id => ['status' => AttendanceStatus::PRESENT->value, 'remarks' => null],
                        $martin->id => ['status' => AttendanceStatus::PRESENT->value, 'remarks' => null],
                    ],
                ];

                foreach ($days as $date => $roster) {
                    foreach ($roster as $studentId => $data) {
                        AttendanceRecord::updateOrCreate(
                            [
                                'school_id' => $greenwood->id,
                                'student_id' => $studentId,
                                'date' => $date,
                            ],
                            [
                                'section_id' => $secA->id,
                                'academic_year_id' => $secA->academic_year_id,
                                'status' => $data['status'],
                                'marked_by' => $edna->id,
                                'remarks' => $data['remarks'],
                            ]
                        );
                    }
                }
            }

            $tenantManager->clearTenant();
        }

        // 2. Maplewood Elementary School Attendance & Calendar
        $maplewood = School::where('subdomain', 'maplewood')->first();
        if ($maplewood) {
            $tenantManager->setTenant($maplewood);

            $mapleYear = AcademicYear::where('school_id', $maplewood->id)
                ->where('name', '2025/2026')
                ->first();

            $holidays = [
                ['date' => '2025-09-01', 'description' => 'Labor Day'],
                ['date' => '2025-11-27', 'description' => 'Thanksgiving Day'],
                ['date' => '2025-12-25', 'description' => 'Winter Break'],
            ];

            foreach ($holidays as $h) {
                SchoolCalendar::updateOrCreate(
                    ['school_id' => $maplewood->id, 'date' => $h['date']],
                    [
                        'academic_year_id' => $mapleYear?->id,
                        'day_type' => 'holiday',
                        'is_school_day' => false,
                        'description' => $h['description'],
                    ]
                );
            }

            $room103 = Section::where('school_id', $maplewood->id)->where('name', 'Room 103')->first();
            $claraTeacher = User::where('email', 'teacher@maplewood.edu')->first();
            $tommy = Student::where('school_id', $maplewood->id)->where('admission_number', 'MAP-25-00101')->first();
            $maya = Student::where('school_id', $maplewood->id)->where('admission_number', 'MAP-25-00102')->first();

            if ($room103 && $claraTeacher && $tommy && $maya) {
                $dates = [
                    '2025-09-02' => [
                        $tommy->id => ['status' => AttendanceStatus::PRESENT->value, 'remarks' => null],
                        $maya->id => ['status' => AttendanceStatus::PRESENT->value, 'remarks' => null],
                    ],
                    '2025-09-03' => [
                        $tommy->id => ['status' => AttendanceStatus::PRESENT->value, 'remarks' => null],
                        $maya->id => ['status' => AttendanceStatus::LATE->value, 'remarks' => 'School bus delay'],
                    ],
                    '2025-09-04' => [
                        $tommy->id => ['status' => AttendanceStatus::PRESENT->value, 'remarks' => null],
                        $maya->id => ['status' => AttendanceStatus::PRESENT->value, 'remarks' => null],
                    ],
                ];

                foreach ($dates as $date => $roster) {
                    foreach ($roster as $studentId => $data) {
                        AttendanceRecord::updateOrCreate(
                            [
                                'school_id' => $maplewood->id,
                                'student_id' => $studentId,
                                'date' => $date,
                            ],
                            [
                                'section_id' => $room103->id,
                                'academic_year_id' => $room103->academic_year_id,
                                'status' => $data['status'],
                                'marked_by' => $claraTeacher->id,
                                'remarks' => $data['remarks'],
                            ]
                        );
                    }
                }
            }

            $tenantManager->clearTenant();
        }
    }
}
