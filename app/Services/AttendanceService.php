<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Models\AcademicYear;
use App\Models\AttendanceRecord;
use App\Models\SchoolCalendar;
use App\Models\Section;
use App\Models\Student;
use App\Models\Term;
use App\Models\User;
use App\Tenancy\Exceptions\ClosedAcademicYearException;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AttendanceService
{
    /**
     * Record daily attendance for a section roster.
     */
    public function recordDailyAttendance(
        Section $section,
        string $date,
        array $records,
        ?string $defaultStatus,
        User $marker
    ): array {
        if ($section->academicYear->isClosed()) {
            throw new ClosedAcademicYearException('Cannot record attendance in a closed academic year.');
        }

        $formattedDate = Carbon::parse($date)->format('Y-m-d');

        // Fetch all active students belonging to this section
        $students = Student::withoutGlobalScopes()
            ->where('school_id', $section->school_id)
            ->where(function ($q) use ($section) {
                $q->where('current_section_id', $section->id)
                  ->orWhereHas('enrollments', function ($eq) use ($section) {
                      $eq->where('section_id', $section->id)
                         ->where('academic_year_id', $section->academic_year_id);
                  });
            })
            ->with('user:id,name,email')
            ->get()
            ->unique('id')
            ->keyBy('id');

        // Map overrides from incoming records by student_id
        $overrideMap = collect($records)->keyBy('student_id');

        $counts = [
            'total' => $students->count(),
            'present' => 0,
            'absent' => 0,
            'late' => 0,
            'excused' => 0,
        ];

        $absenceNotifications = [];

        DB::beginTransaction();
        try {
            foreach ($students as $studentId => $student) {
                $override = $overrideMap->get($studentId);

                $status = $override['status'] 
                    ?? $defaultStatus 
                    ?? AttendanceStatus::PRESENT->value;

                $remarks = $override['remarks'] ?? null;

                // Validate status is valid enum
                if (! in_array($status, AttendanceStatus::values(), true)) {
                    $status = AttendanceStatus::PRESENT->value;
                }

                $record = AttendanceRecord::updateOrCreate(
                    [
                        'school_id' => $section->school_id,
                        'student_id' => $student->id,
                        'date' => $formattedDate,
                    ],
                    [
                        'section_id' => $section->id,
                        'academic_year_id' => $section->academic_year_id,
                        'status' => $status,
                        'marked_by' => $marker->id,
                        'remarks' => $remarks,
                    ]
                );

                if (isset($counts[$status])) {
                    $counts[$status]++;
                }

                // If marked absent, trigger absence notification hook
                if ($status === AttendanceStatus::ABSENT->value) {
                    $absenceNotifications[] = AbsenceNotificationHook::trigger($student, $record);
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $attended = $counts['present'] + $counts['late'];
        $counts['attendance_percentage'] = $counts['total'] > 0 
            ? round(($attended / $counts['total']) * 100, 1) 
            : 100.0;

        $counts['date'] = $formattedDate;
        $counts['section_id'] = $section->id;
        $counts['section_name'] = $section->name;
        $counts['absence_notifications_count'] = count($absenceNotifications);

        return $counts;
    }

    /**
     * Get section daily attendance roster and statistics for a given date.
     */
    public function getSectionDailyAttendance(Section $section, string $date): array
    {
        $formattedDate = Carbon::parse($date)->format('Y-m-d');
        $isSchoolDay = SchoolCalendar::isSchoolDay($formattedDate, $section->school_id);

        $students = Student::withoutGlobalScopes()
            ->where('school_id', $section->school_id)
            ->where(function ($q) use ($section) {
                $q->where('current_section_id', $section->id)
                  ->orWhereHas('enrollments', function ($eq) use ($section) {
                      $eq->where('section_id', $section->id)
                         ->where('academic_year_id', $section->academic_year_id);
                  });
            })
            ->with(['user:id,name,email,phone', 'parents.user:id,name,email,phone'])
            ->get()
            ->unique('id');

        $existingRecords = AttendanceRecord::withoutGlobalScopes()
            ->where('school_id', $section->school_id)
            ->where('section_id', $section->id)
            ->whereDate('date', $formattedDate)
            ->with('marker:id,name,email')
            ->get()
            ->keyBy('student_id');

        $roster = [];
        $counts = [
            'total' => $students->count(),
            'present' => 0,
            'absent' => 0,
            'late' => 0,
            'excused' => 0,
            'unrecorded' => 0,
        ];

        foreach ($students as $student) {
            $record = $existingRecords->get($student->id);
            $status = $record?->status;

            if ($status && isset($counts[$status])) {
                $counts[$status]++;
            } else {
                $counts['unrecorded']++;
            }

            $roster[] = [
                'student_id' => $student->id,
                'name' => $student->user?->name,
                'admission_number' => $student->admission_number,
                'status' => $status,
                'remarks' => $record?->remarks,
                'marked_by' => $record?->marker?->name,
                'marked_at' => $record?->updated_at?->toIso8601String(),
            ];
        }

        $recordedCount = $counts['total'] - $counts['unrecorded'];
        $attendedCount = $counts['present'] + $counts['late'];

        $counts['recorded_count'] = $recordedCount;
        $counts['is_fully_marked'] = $counts['total'] > 0 && $counts['unrecorded'] === 0;
        $counts['attendance_percentage'] = $recordedCount > 0 
            ? round(($attendedCount / $recordedCount) * 100, 1) 
            : null;

        return [
            'section' => [
                'id' => $section->id,
                'name' => $section->name,
                'grade_level' => $section->gradeLevel?->name,
                'homeroom_teacher' => $section->homeroomTeacher?->name,
            ],
            'date' => $formattedDate,
            'is_school_day' => $isSchoolDay,
            'stats' => $counts,
            'roster' => $roster,
        ];
    }

    /**
     * Compute comprehensive attendance summary for a student over an academic year, term, or custom date range.
     * Excludes non-school days (weekends & holidays) via SchoolCalendar.
     */
    public function getStudentAttendanceSummary(
        Student $student,
        ?int $academicYearId = null,
        ?int $termId = null,
        ?string $startDate = null,
        ?string $endDate = null
    ): array {
        $schoolId = $student->school_id;

        // Resolve date boundaries
        if ($termId) {
            $term = Term::withoutGlobalScopes()->where('school_id', $schoolId)->find($termId);
            if ($term) {
                $startDate = $term->start_date;
                $endDate = min(Carbon::parse($term->end_date)->format('Y-m-d'), now()->format('Y-m-d'));
                $academicYearId = $term->academic_year_id;
            }
        }

        if (! $startDate || ! $endDate) {
            $year = $academicYearId 
                ? AcademicYear::withoutGlobalScopes()->where('school_id', $schoolId)->find($academicYearId)
                : AcademicYear::withoutGlobalScopes()->where('school_id', $schoolId)->where('is_active', true)->first();

            if ($year) {
                $startDate = $startDate ?: Carbon::parse($year->start_date)->format('Y-m-d');
                $endDate = $endDate ?: min(Carbon::parse($year->end_date)->format('Y-m-d'), now()->format('Y-m-d'));
                $academicYearId = $year->id;
            } else {
                $startDate = $startDate ?: now()->startOfMonth()->format('Y-m-d');
                $endDate = $endDate ?: now()->format('Y-m-d');
            }
        }

        // Total school days in this period (excluding weekends & holidays in school_calendar)
        $totalSchoolDays = SchoolCalendar::getSchoolDaysCount($startDate, $endDate, $schoolId);

        // Fetch student's attendance records in this period
        $records = AttendanceRecord::withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->where('student_id', $student->id)
            ->whereBetween('date', [$startDate, $endDate])
            ->orderBy('date', 'desc')
            ->get();

        $presentDays = $records->where('status', AttendanceStatus::PRESENT->value)->count();
        $lateDays = $records->where('status', AttendanceStatus::LATE->value)->count();
        $absentDays = $records->where('status', AttendanceStatus::ABSENT->value)->count();
        $excusedDays = $records->where('status', AttendanceStatus::EXCUSED->value)->count();

        $attendedDays = $presentDays + $lateDays;

        // Effective divisor: ensure school days denominator is at least attended+absent+excused
        $recordedDaysCount = $records->count();
        $effectiveTotalDays = max($totalSchoolDays, $recordedDaysCount);

        $attendancePercentage = $effectiveTotalDays > 0 
            ? round(($attendedDays / $effectiveTotalDays) * 100, 1) 
            : 100.0;

        // Breakdown by terms if academic year is known
        $byTerm = [];
        if ($academicYearId) {
            $terms = Term::withoutGlobalScopes()
                ->where('school_id', $schoolId)
                ->where('academic_year_id', $academicYearId)
                ->get();

            foreach ($terms as $t) {
                $tEnd = min(Carbon::parse($t->end_date)->format('Y-m-d'), now()->format('Y-m-d'));
                $tStart = Carbon::parse($t->start_date)->format('Y-m-d');

                if (Carbon::parse($tStart)->gt(Carbon::parse($tEnd))) {
                    continue;
                }

                $tSchoolDays = SchoolCalendar::getSchoolDaysCount($tStart, $tEnd, $schoolId);
                $tRecords = AttendanceRecord::withoutGlobalScopes()
                    ->where('school_id', $schoolId)
                    ->where('student_id', $student->id)
                    ->whereBetween('date', [$tStart, $tEnd])
                    ->get();

                $tPresent = $tRecords->where('status', AttendanceStatus::PRESENT->value)->count();
                $tLate = $tRecords->where('status', AttendanceStatus::LATE->value)->count();
                $tAbsent = $tRecords->where('status', AttendanceStatus::ABSENT->value)->count();
                $tExcused = $tRecords->where('status', AttendanceStatus::EXCUSED->value)->count();
                $tAttended = $tPresent + $tLate;
                $tEffectiveDays = max($tSchoolDays, $tRecords->count());

                $byTerm[] = [
                    'term_id' => $t->id,
                    'name' => $t->name,
                    'is_active' => $t->is_active,
                    'school_days' => $tSchoolDays,
                    'present' => $tPresent,
                    'late' => $tLate,
                    'absent' => $tAbsent,
                    'excused' => $tExcused,
                    'attended' => $tAttended,
                    'percentage' => $tEffectiveDays > 0 ? round(($tAttended / $tEffectiveDays) * 100, 1) : 100.0,
                ];
            }
        }

        return [
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'total_school_days' => $totalSchoolDays,
            ],
            'summary' => [
                'total_school_days' => $totalSchoolDays,
                'present_days' => $presentDays,
                'late_days' => $lateDays,
                'absent_days' => $absentDays,
                'excused_days' => $excusedDays,
                'attended_days' => $attendedDays,
                'attendance_percentage' => $attendancePercentage,
            ],
            'by_term' => $byTerm,
            'recent_records' => $records->take(30)->values(),
        ];
    }

    /**
     * Compute section attendance summary and daily trends over a date range.
     */
    public function getSectionAttendanceSummary(Section $section, ?string $startDate = null, ?string $endDate = null): array
    {
        $schoolId = $section->school_id;
        $endDate = $endDate ?: now()->format('Y-m-d');
        $startDate = $startDate ?: now()->subDays(14)->format('Y-m-d');

        $schoolDates = SchoolCalendar::getSchoolDatesBetween($startDate, $endDate, $schoolId);

        $studentsCount = Student::withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->where('current_section_id', $section->id)
            ->count();

        $dailyTrends = [];
        $totalPresentAcrossDays = 0;
        $totalLateAcrossDays = 0;
        $totalAbsentAcrossDays = 0;
        $totalExcusedAcrossDays = 0;
        $recordedDaysCount = 0;

        foreach ($schoolDates as $dateStr) {
            $records = AttendanceRecord::withoutGlobalScopes()
                ->where('school_id', $schoolId)
                ->where('section_id', $section->id)
                ->whereDate('date', $dateStr)
                ->get();

            if ($records->isEmpty()) {
                continue;
            }

            $recordedDaysCount++;
            $present = $records->where('status', AttendanceStatus::PRESENT->value)->count();
            $late = $records->where('status', AttendanceStatus::LATE->value)->count();
            $absent = $records->where('status', AttendanceStatus::ABSENT->value)->count();
            $excused = $records->where('status', AttendanceStatus::EXCUSED->value)->count();
            $totalRosterForDay = max($studentsCount, $records->count());
            $attended = $present + $late;
            $dayPercentage = $totalRosterForDay > 0 ? round(($attended / $totalRosterForDay) * 100, 1) : 0;

            $totalPresentAcrossDays += $present;
            $totalLateAcrossDays += $late;
            $totalAbsentAcrossDays += $absent;
            $totalExcusedAcrossDays += $excused;

            $dailyTrends[] = [
                'date' => $dateStr,
                'present' => $present,
                'late' => $late,
                'absent' => $absent,
                'excused' => $excused,
                'attendance_percentage' => $dayPercentage,
            ];
        }

        $totalAttended = $totalPresentAcrossDays + $totalLateAcrossDays;
        $totalStudentDays = $recordedDaysCount * max(1, $studentsCount);
        $averageAttendancePercentage = $totalStudentDays > 0 
            ? round(($totalAttended / $totalStudentDays) * 100, 1) 
            : 100.0;

        return [
            'section' => [
                'id' => $section->id,
                'name' => $section->name,
                'grade_level' => $section->gradeLevel?->name,
                'students_count' => $studentsCount,
            ],
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'school_days_in_period' => $schoolDates->count(),
                'recorded_school_days' => $recordedDaysCount,
            ],
            'overall_summary' => [
                'average_attendance_percentage' => $averageAttendancePercentage,
                'total_present' => $totalPresentAcrossDays,
                'total_late' => $totalLateAcrossDays,
                'total_absent' => $totalAbsentAcrossDays,
                'total_excused' => $totalExcusedAcrossDays,
            ],
            'daily_trends' => $dailyTrends,
        ];
    }
}
