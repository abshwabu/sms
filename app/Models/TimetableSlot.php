<?php

namespace App\Models;

use App\Enums\DayOfWeek;
use App\Tenancy\Exceptions\ClosedAcademicYearException;
use App\Tenancy\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimetableSlot extends Model
{
    use HasFactory, TenantScoped;

    protected $table = 'timetable_slots';

    protected $fillable = [
        'school_id',
        'academic_year_id',
        'section_id',
        'subject_id',
        'teacher_id',
        'day_of_week',
        'period_number',
        'start_time',
        'end_time',
        'room',
        'color',
    ];

    protected $casts = [
        'period_number' => 'integer',
        'day_of_week' => DayOfWeek::class,
    ];

    protected static function booted(): void
    {
        static::saving(function ($slot) {
            $year = AcademicYear::withoutGlobalScopes()->find($slot->academic_year_id);
            if ($year && $year->isClosed()) {
                throw new ClosedAcademicYearException('Cannot create or update timetable slots in a closed academic year.');
            }
        });

        static::deleting(function ($slot) {
            $year = AcademicYear::withoutGlobalScopes()->find($slot->academic_year_id);
            if ($year && $year->isClosed()) {
                throw new ClosedAcademicYearException('Cannot delete timetable slots from a closed academic year.');
            }
        });
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }
}
