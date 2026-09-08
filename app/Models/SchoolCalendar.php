<?php

namespace App\Models;

use App\Tenancy\Traits\TenantScoped;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

class SchoolCalendar extends Model
{
    use HasFactory, TenantScoped;

    protected $table = 'school_calendar';

    protected $fillable = [
        'school_id',
        'academic_year_id',
        'date',
        'day_type',
        'is_school_day',
        'description',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'is_school_day' => 'boolean',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /**
     * Determine whether a specific date is considered a school day for a given school.
     * Checks explicit school_calendar overrides; defaults to Monday-Friday weekdays.
     */
    public static function isSchoolDay(string|Carbon $date, int $schoolId): bool
    {
        $parsed = Carbon::parse($date)->format('Y-m-d');

        $calendarDay = static::withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->whereDate('date', $parsed)
            ->first();

        if ($calendarDay !== null) {
            return (bool) $calendarDay->is_school_day;
        }

        // By default, Monday-Friday are school days, Saturday-Sunday are weekends
        return Carbon::parse($parsed)->isWeekday();
    }

    /**
     * Get all school day dates (as 'Y-m-d' strings) between two dates for a school.
     */
    public static function getSchoolDatesBetween(string|Carbon $startDate, string|Carbon $endDate, int $schoolId): Collection
    {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->startOfDay();

        if ($start->gt($end)) {
            return collect([]);
        }

        // Fetch all explicit overrides for this range
        $overrides = static::withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->whereBetween('date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->get()
            ->keyBy(fn ($item) => Carbon::parse($item->date)->format('Y-m-d'));

        $period = CarbonPeriod::create($start, $end);
        $schoolDates = collect([]);

        foreach ($period as $currentDate) {
            $formatted = $currentDate->format('Y-m-d');

            if ($overrides->has($formatted)) {
                if ($overrides->get($formatted)->is_school_day) {
                    $schoolDates->push($formatted);
                }
            } else {
                if ($currentDate->isWeekday()) {
                    $schoolDates->push($formatted);
                }
            }
        }

        return $schoolDates;
    }

    /**
     * Get count of school days between two dates for a school.
     */
    public static function getSchoolDaysCount(string|Carbon $startDate, string|Carbon $endDate, int $schoolId): int
    {
        return static::getSchoolDatesBetween($startDate, $endDate, $schoolId)->count();
    }
}
