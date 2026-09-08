<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use App\Tenancy\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceRecord extends Model
{
    use HasFactory, TenantScoped;

    protected $table = 'attendance_records';

    protected $fillable = [
        'school_id',
        'section_id',
        'student_id',
        'academic_year_id',
        'date',
        'status',
        'marked_by',
        'remarks',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function marker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by');
    }

    /**
     * Helper to check if this record counts as attendance (present or late).
     */
    public function isAttended(): bool
    {
        return in_array($this->status, [AttendanceStatus::PRESENT->value, AttendanceStatus::LATE->value], true);
    }

    public function scopeForSection(Builder $query, int|Section $section): Builder
    {
        $id = $section instanceof Section ? $section->id : $section;
        return $query->where('section_id', $id);
    }

    public function scopeForStudent(Builder $query, int|Student $student): Builder
    {
        $id = $student instanceof Student ? $student->id : $student;
        return $query->where('student_id', $id);
    }

    public function scopeForDate(Builder $query, string $date): Builder
    {
        return $query->whereDate('date', $date);
    }

    public function scopeDateBetween(Builder $query, string $startDate, string $endDate): Builder
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }
}
