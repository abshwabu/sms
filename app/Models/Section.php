<?php

namespace App\Models;

use App\Tenancy\Exceptions\ClosedAcademicYearException;
use App\Tenancy\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Section extends Model
{
    use HasFactory, TenantScoped;

    protected $fillable = [
        'school_id',
        'academic_year_id',
        'grade_level_id',
        'name',
        'capacity',
        'homeroom_teacher_id',
    ];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function ($section) {
            if ($section->academicYear && $section->academicYear->isClosed()) {
                throw new ClosedAcademicYearException('Cannot add or modify sections in a closed academic year.');
            }
        });

        static::deleting(function ($section) {
            if ($section->academicYear && $section->academicYear->isClosed()) {
                throw new ClosedAcademicYearException('Cannot delete sections in a closed academic year.');
            }
        });
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function gradeLevel(): BelongsTo
    {
        return $this->belongsTo(GradeLevel::class);
    }

    public function homeroomTeacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'homeroom_teacher_id');
    }

    public function studentAssignments(): HasMany
    {
        return $this->hasMany(StudentSectionAssignment::class);
    }

    public function enrolledCount(): int
    {
        return $this->studentAssignments()->where('status', 'enrolled')->count();
    }

    public function hasAvailableCapacity(): bool
    {
        return $this->enrolledCount() < $this->capacity;
    }
}
