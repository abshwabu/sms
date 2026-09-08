<?php

namespace App\Models;

use App\Tenancy\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubjectOffering extends Model
{
    use HasFactory, TenantScoped;

    protected $fillable = [
        'school_id',
        'academic_year_id',
        'grade_level_id',
        'subject_id',
        'type',
        'max_students',
        'enrollment_start',
        'enrollment_end',
        'is_open',
    ];

    protected function casts(): array
    {
        return [
            'max_students' => 'integer',
            'enrollment_start' => 'datetime',
            'enrollment_end' => 'datetime',
            'is_open' => 'boolean',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function gradeLevel(): BelongsTo
    {
        return $this->belongsTo(GradeLevel::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function studentSelections(): HasMany
    {
        return $this->hasMany(StudentSubjectSelection::class, 'subject_id', 'subject_id')
            ->where('academic_year_id', $this->academic_year_id)
            ->where('status', 'enrolled');
    }

    /**
     * Active student enrollments count for this offering.
     */
    public function enrolledCount(): int
    {
        return StudentSubjectSelection::withoutGlobalScopes()
            ->where('school_id', $this->school_id)
            ->where('academic_year_id', $this->academic_year_id)
            ->where('subject_id', $this->subject_id)
            ->where('status', 'enrolled')
            ->count();
    }

    /**
     * Remaining capacity for this offering (null if unlimited).
     */
    public function remainingCapacity(): ?int
    {
        if ($this->max_students === null) {
            return null;
        }

        return max(0, $this->max_students - $this->enrolledCount());
    }

    /**
     * Determine if this elective offering is currently full.
     */
    public function isFull(): bool
    {
        if ($this->max_students === null) {
            return false;
        }

        return $this->enrolledCount() >= $this->max_students;
    }

    /**
     * Check if the enrollment window is open.
     */
    public function isWindowOpen(): bool
    {
        if (! $this->is_open) {
            return false;
        }

        $now = now();

        if ($this->enrollment_start && $now->lt($this->enrollment_start)) {
            return false;
        }

        if ($this->enrollment_end && $now->gt($this->enrollment_end)) {
            return false;
        }

        return true;
    }

    /**
     * Determine if a student can currently enroll.
     */
    public function canEnroll(): bool
    {
        return $this->isWindowOpen() && ! $this->isFull();
    }

    /**
     * Check if type is elective.
     */
    public function isElective(): bool
    {
        return $this->type === 'elective';
    }

    /**
     * Check if type is core.
     */
    public function isCore(): bool
    {
        return $this->type === 'core';
    }

    public function scopeElectives(Builder $query): Builder
    {
        return $query->where('type', 'elective');
    }

    public function scopeCore(Builder $query): Builder
    {
        return $query->where('type', 'core');
    }

    public function scopeForAcademicYear(Builder $query, int $academicYearId): Builder
    {
        return $query->where('academic_year_id', $academicYearId);
    }

    public function scopeForGrade(Builder $query, int $gradeLevelId): Builder
    {
        return $query->where('grade_level_id', $gradeLevelId);
    }
}
