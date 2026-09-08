<?php

namespace App\Services;

use App\Enums\DayOfWeek;
use App\Exceptions\TimetableConflictException;
use App\Models\AcademicYear;
use App\Models\Section;
use App\Models\Subject;
use App\Models\TimetableSlot;
use App\Models\User;
use App\Tenancy\Exceptions\ClosedAcademicYearException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class TimetableService
{
    /**
     * Default time range mapping for standard school periods.
     */
    public static function defaultPeriodTimes(int $periodNumber): array
    {
        $periods = [
            1 => ['start' => '08:00', 'end' => '08:50'],
            2 => ['start' => '09:00', 'end' => '09:50'],
            3 => ['start' => '10:00', 'end' => '10:50'],
            4 => ['start' => '11:00', 'end' => '11:50'],
            5 => ['start' => '12:30', 'end' => '13:20'],
            6 => ['start' => '13:30', 'end' => '14:20'],
            7 => ['start' => '14:30', 'end' => '15:20'],
            8 => ['start' => '15:30', 'end' => '16:20'],
        ];

        return $periods[$periodNumber] ?? ['start' => '08:00', 'end' => '08:50'];
    }

    /**
     * Conflict detection:
     * 1. Teacher cannot be double-booked across two sections at the same slot.
     * 2. Section cannot have two classes scheduled for the same day and period.
     * 3. Room cannot be double-booked by two different sections at the same time.
     *
     * @throws TimetableConflictException
     */
    public function detectConflicts(
        int $schoolId,
        int $academicYearId,
        int $sectionId,
        string $dayOfWeek,
        int $periodNumber,
        ?string $startTime = null,
        ?string $endTime = null,
        ?int $teacherId = null,
        ?string $room = null,
        ?int $ignoreSlotId = null
    ): void {
        $dayOfWeek = strtolower($dayOfWeek);

        // 1. Section Conflict: does this section already have a slot at this day & period?
        $sectionConflict = TimetableSlot::withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->where('section_id', $sectionId)
            ->where('day_of_week', $dayOfWeek)
            ->where('period_number', $periodNumber)
            ->when($ignoreSlotId, fn($q) => $q->where('id', '!=', $ignoreSlotId))
            ->with(['subject', 'section'])
            ->first();

        if ($sectionConflict) {
            $sectionName = $sectionConflict->section?->name ?? 'Section';
            $subjectName = $sectionConflict->subject?->name ?? 'another subject';
            throw new TimetableConflictException(
                "{$sectionName} already has {$subjectName} scheduled on " . ucfirst($dayOfWeek) . " during Period {$periodNumber}.",
                [
                    'conflict_type' => 'section',
                    'section_id' => $sectionId,
                    'section_name' => $sectionName,
                    'day_of_week' => $dayOfWeek,
                    'period_number' => $periodNumber,
                    'existing_subject' => $subjectName,
                ]
            );
        }

        // 2. Teacher Conflict: teacher cannot be double-booked across sections at the same slot
        if ($teacherId) {
            $teacherConflict = TimetableSlot::withoutGlobalScopes()
                ->where('school_id', $schoolId)
                ->where('academic_year_id', $academicYearId)
                ->where('teacher_id', $teacherId)
                ->where('day_of_week', $dayOfWeek)
                ->where('period_number', $periodNumber)
                ->when($ignoreSlotId, fn($q) => $q->where('id', '!=', $ignoreSlotId))
                ->with(['teacher', 'section', 'subject'])
                ->first();

            if ($teacherConflict) {
                $teacherName = $teacherConflict->teacher?->name ?? 'Teacher';
                $otherSectionName = $teacherConflict->section?->name ?? 'another section';
                $timeRange = ($teacherConflict->start_time && $teacherConflict->end_time)
                    ? " ({$teacherConflict->start_time} - {$teacherConflict->end_time})"
                    : "";

                throw new TimetableConflictException(
                    "Teacher {$teacherName} is already scheduled in {$otherSectionName} on " . ucfirst($dayOfWeek) . " during Period {$periodNumber}{$timeRange}.",
                    [
                        'conflict_type' => 'teacher',
                        'teacher_id' => $teacherId,
                        'teacher_name' => $teacherName,
                        'conflicting_section_id' => $teacherConflict->section_id,
                        'conflicting_section_name' => $otherSectionName,
                        'day_of_week' => $dayOfWeek,
                        'period_number' => $periodNumber,
                        'time_range' => trim($timeRange),
                    ]
                );
            }
        }

        // 3. Room Conflict: room cannot be double-booked
        if (! empty($room)) {
            $roomConflict = TimetableSlot::withoutGlobalScopes()
                ->where('school_id', $schoolId)
                ->where('academic_year_id', $academicYearId)
                ->where('room', $room)
                ->where('day_of_week', $dayOfWeek)
                ->where('period_number', $periodNumber)
                ->when($ignoreSlotId, fn($q) => $q->where('id', '!=', $ignoreSlotId))
                ->with('section')
                ->first();

            if ($roomConflict) {
                $otherSectionName = $roomConflict->section?->name ?? 'another class';
                throw new TimetableConflictException(
                    "Room '{$room}' is already booked by {$otherSectionName} on " . ucfirst($dayOfWeek) . " during Period {$periodNumber}.",
                    [
                        'conflict_type' => 'room',
                        'room' => $room,
                        'conflicting_section_id' => $roomConflict->section_id,
                        'conflicting_section_name' => $otherSectionName,
                        'day_of_week' => $dayOfWeek,
                        'period_number' => $periodNumber,
                    ]
                );
            }
        }
    }

    /**
     * Create a new timetable slot with conflict verification.
     */
    public function createSlot(Section $section, array $data): TimetableSlot
    {
        if ($section->academicYear && $section->academicYear->isClosed()) {
            throw new ClosedAcademicYearException('Cannot create timetable slots in a closed academic year.');
        }

        $periodNumber = (int) $data['period_number'];
        $defaultTimes = self::defaultPeriodTimes($periodNumber);

        $startTime = $data['start_time'] ?? $defaultTimes['start'];
        $endTime = $data['end_time'] ?? $defaultTimes['end'];
        $dayOfWeek = strtolower($data['day_of_week'] instanceof DayOfWeek ? $data['day_of_week']->value : $data['day_of_week']);
        $teacherId = ! empty($data['teacher_id']) ? (int) $data['teacher_id'] : null;
        $room = $data['room'] ?? null;

        $this->detectConflicts(
            schoolId: $section->school_id,
            academicYearId: $section->academic_year_id,
            sectionId: $section->id,
            dayOfWeek: $dayOfWeek,
            periodNumber: $periodNumber,
            startTime: $startTime,
            endTime: $endTime,
            teacherId: $teacherId,
            room: $room
        );

        $slot = TimetableSlot::create([
            'school_id' => $section->school_id,
            'academic_year_id' => $section->academic_year_id,
            'section_id' => $section->id,
            'subject_id' => (int) $data['subject_id'],
            'teacher_id' => $teacherId,
            'day_of_week' => $dayOfWeek,
            'period_number' => $periodNumber,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'room' => $room,
            'color' => $data['color'] ?? null,
        ]);

        return $slot->load(['subject', 'teacher', 'section.gradeLevel']);
    }

    /**
     * Update an existing timetable slot with conflict verification.
     */
    public function updateSlot(TimetableSlot $slot, array $data): TimetableSlot
    {
        $academicYear = $slot->academicYear;
        if ($academicYear && $academicYear->isClosed()) {
            throw new ClosedAcademicYearException('Cannot modify timetable slots in a closed academic year.');
        }

        $periodNumber = isset($data['period_number']) ? (int) $data['period_number'] : $slot->period_number;
        $defaultTimes = self::defaultPeriodTimes($periodNumber);

        $startTime = $data['start_time'] ?? ($slot->start_time ?: $defaultTimes['start']);
        $endTime = $data['end_time'] ?? ($slot->end_time ?: $defaultTimes['end']);
        $dayOfWeek = isset($data['day_of_week']) 
            ? strtolower($data['day_of_week'] instanceof DayOfWeek ? $data['day_of_week']->value : $data['day_of_week']) 
            : $slot->day_of_week->value;

        $teacherId = array_key_exists('teacher_id', $data) 
            ? ($data['teacher_id'] ? (int) $data['teacher_id'] : null) 
            : $slot->teacher_id;

        $room = array_key_exists('room', $data) ? $data['room'] : $slot->room;
        $subjectId = isset($data['subject_id']) ? (int) $data['subject_id'] : $slot->subject_id;

        $this->detectConflicts(
            schoolId: $slot->school_id,
            academicYearId: $slot->academic_year_id,
            sectionId: $slot->section_id,
            dayOfWeek: $dayOfWeek,
            periodNumber: $periodNumber,
            startTime: $startTime,
            endTime: $endTime,
            teacherId: $teacherId,
            room: $room,
            ignoreSlotId: $slot->id
        );

        $slot->update([
            'subject_id' => $subjectId,
            'teacher_id' => $teacherId,
            'day_of_week' => $dayOfWeek,
            'period_number' => $periodNumber,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'room' => $room,
            'color' => $data['color'] ?? $slot->color,
        ]);

        return $slot->fresh(['subject', 'teacher', 'section.gradeLevel']);
    }

    /**
     * Delete a timetable slot.
     */
    public function deleteSlot(TimetableSlot $slot): bool
    {
        if ($slot->academicYear && $slot->academicYear->isClosed()) {
            throw new ClosedAcademicYearException('Cannot delete timetable slots in a closed academic year.');
        }

        return $slot->delete();
    }

    /**
     * Bulk build or replace a section's weekly timetable.
     */
    public function batchCreateSlots(Section $section, array $slotsData, bool $replaceExisting = false): \Illuminate\Support\Collection
    {
        if ($section->academicYear && $section->academicYear->isClosed()) {
            throw new ClosedAcademicYearException('Cannot create timetable slots in a closed academic year.');
        }

        $createdSlots = collect();

        DB::beginTransaction();
        try {
            if ($replaceExisting) {
                TimetableSlot::withoutGlobalScopes()
                    ->where('school_id', $section->school_id)
                    ->where('section_id', $section->id)
                    ->where('academic_year_id', $section->academic_year_id)
                    ->delete();
            }

            foreach ($slotsData as $data) {
                $createdSlots->push($this->createSlot($section, $data));
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return $createdSlots;
    }

    /**
     * Retrieve weekly timetable for a section with structured grid format.
     */
    public function getSectionTimetable(Section $section, ?string $dayOfWeek = null): array
    {
        $query = TimetableSlot::withoutGlobalScopes()
            ->where('school_id', $section->school_id)
            ->where('section_id', $section->id)
            ->where('academic_year_id', $section->academic_year_id)
            ->with(['subject', 'teacher', 'section.gradeLevel']);

        if ($dayOfWeek) {
            $query->where('day_of_week', strtolower($dayOfWeek));
        }

        $slots = $query->orderBy('period_number')->get();

        $days = DayOfWeek::schoolDays();
        $periods = [1, 2, 3, 4, 5, 6, 7, 8];

        // Format 2D weekly matrix grid: grid[day][period]
        $grid = [];
        foreach ($days as $day) {
            $grid[$day] = [];
            foreach ($periods as $p) {
                $grid[$day][$p] = null;
            }
        }

        foreach ($slots as $slot) {
            $d = $slot->day_of_week instanceof DayOfWeek ? $slot->day_of_week->value : $slot->day_of_week;
            $p = (int) $slot->period_number;
            if (isset($grid[$d])) {
                $grid[$d][$p] = $slot;
            }
        }

        // Summary statistics
        $subjectBreakdown = $slots->groupBy('subject_id')->map(function ($group) {
            $sub = $group->first()->subject;
            return [
                'subject_id' => $sub?->id,
                'name' => $sub?->name,
                'code' => $sub?->code,
                'periods_count' => $group->count(),
            ];
        })->values();

        return [
            'section' => [
                'id' => $section->id,
                'name' => $section->name,
                'grade_level' => $section->gradeLevel?->name,
                'homeroom_teacher' => $section->homeroomTeacher?->name,
            ],
            'academic_year' => [
                'id' => $section->academicYear?->id,
                'name' => $section->academicYear?->name,
            ],
            'days' => $days,
            'periods' => array_map(fn($p) => [
                'period_number' => $p,
                'times' => self::defaultPeriodTimes($p),
            ], $periods),
            'slots' => $slots,
            'grid' => $grid,
            'stats' => [
                'total_slots' => $slots->count(),
                'subjects' => $subjectBreakdown,
            ],
        ];
    }

    /**
     * Acceptance criterion: A teacher's personal timetable correctly aggregates slots
     * across every section they teach.
     */
    public function getTeacherTimetable(User $teacher, ?int $academicYearId = null): array
    {
        $schoolId = $teacher->school_id;

        $academicYear = $academicYearId
            ? AcademicYear::withoutGlobalScopes()->where('school_id', $schoolId)->find($academicYearId)
            : AcademicYear::withoutGlobalScopes()->where('school_id', $schoolId)->where('is_active', true)->first();

        $query = TimetableSlot::withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->where('teacher_id', $teacher->id);

        if ($academicYear) {
            $query->where('academic_year_id', $academicYear->id);
        }

        $slots = $query->with(['subject', 'section.gradeLevel'])
            ->orderBy('period_number')
            ->get();

        $days = DayOfWeek::schoolDays();
        $periods = [1, 2, 3, 4, 5, 6, 7, 8];

        $grid = [];
        foreach ($days as $day) {
            $grid[$day] = [];
            foreach ($periods as $p) {
                $grid[$day][$p] = null;
            }
        }

        $sectionsTaught = [];

        foreach ($slots as $slot) {
            $d = $slot->day_of_week instanceof DayOfWeek ? $slot->day_of_week->value : $slot->day_of_week;
            $p = (int) $slot->period_number;
            if (isset($grid[$d])) {
                $grid[$d][$p] = $slot;
            }

            if ($slot->section) {
                $sectionsTaught[$slot->section_id] = [
                    'id' => $slot->section->id,
                    'name' => $slot->section->name,
                    'grade_level' => $slot->section->gradeLevel?->name,
                ];
            }
        }

        return [
            'teacher' => [
                'id' => $teacher->id,
                'name' => $teacher->name,
                'email' => $teacher->email,
                'role' => $teacher->role,
            ],
            'academic_year' => $academicYear ? [
                'id' => $academicYear->id,
                'name' => $academicYear->name,
            ] : null,
            'sections_taught' => array_values($sectionsTaught),
            'days' => $days,
            'periods' => array_map(fn($p) => [
                'period_number' => $p,
                'times' => self::defaultPeriodTimes($p),
            ], $periods),
            'slots' => $slots,
            'grid' => $grid,
            'stats' => [
                'total_weekly_periods' => $slots->count(),
                'sections_count' => count($sectionsTaught),
                'distinct_subjects_count' => $slots->pluck('subject_id')->unique()->count(),
            ],
        ];
    }
}
