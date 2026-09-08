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
            $year = AcademicYear::withoutGlobalScopes()->find($section->academic_year_id);
            if ($year && $year->isClosed()) {
                throw new ClosedAcademicYearException('Cannot add or modify sections in a closed academic year.');
            }
        });

        static::deleting(function ($section) {
            $year = AcademicYear::withoutGlobalScopes()->find($section->academic_year_id);
            if ($year && $year->isClosed()) {
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

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function subjectTeachers(): HasMany
    {
        return $this->hasMany(SectionSubjectTeacher::class);
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class)->orderBy('date', 'desc');
    }

    /**
     * Determine if a user or staff member is the homeroom teacher of this section.
     */
    public function isHomeroomTeacher(User|Staff|int $userOrStaff): bool
    {
        $userId = $this->resolveUserId($userOrStaff);
        return $userId !== null && (int) $this->homeroom_teacher_id === (int) $userId;
    }

    /**
     * Determine if a user or staff member teaches in this section (homeroom or subject).
     */
    public function hasTeacher(User|Staff|int $userOrStaff): bool
    {
        if ($this->isHomeroomTeacher($userOrStaff)) {
            return true;
        }

        $staffId = $this->resolveStaffId($userOrStaff);
        if (! $staffId) {
            return false;
        }

        return $this->subjectTeachers()->where('staff_id', $staffId)->exists();
    }

    /**
     * Check if a teacher can enter attendance for this section.
     * Homeroom teacher or any assigned subject teacher can record attendance.
     */
    public function canTeacherTakeAttendance(User|Staff|int $userOrStaff): bool
    {
        return $this->hasTeacher($userOrStaff);
    }

    /**
     * Check if a teacher can enter grades for a specific subject in this section.
     * Homeroom teacher has master grading oversight, or assigned subject teacher.
     */
    public function canTeacherGrade(User|Staff|int $userOrStaff, ?int $courseId = null): bool
    {
        if ($this->isHomeroomTeacher($userOrStaff)) {
            return true;
        }

        $staffId = $this->resolveStaffId($userOrStaff);
        if (! $staffId) {
            return false;
        }

        $query = $this->subjectTeachers()->where('staff_id', $staffId);

        if ($courseId !== null) {
            $query->where('course_id', $courseId);
        }

        return $query->exists();
    }

    protected function resolveUserId(User|Staff|int $userOrStaff): ?int
    {
        if ($userOrStaff instanceof User) {
            return $userOrStaff->id;
        }
        if ($userOrStaff instanceof Staff) {
            return $userOrStaff->user_id;
        }
        return (int) $userOrStaff;
    }

    protected function resolveStaffId(User|Staff|int $userOrStaff): ?int
    {
        if ($userOrStaff instanceof Staff) {
            return $userOrStaff->id;
        }
        if ($userOrStaff instanceof User) {
            return $userOrStaff->staff?->id ?? Staff::where('user_id', $userOrStaff->id)->value('id');
        }
        return (int) $userOrStaff;
    }

    public function enrolledCount(): int
    {
        $count = $this->enrollments()->where('status', 'enrolled')->count();
        if ($count > 0) {
            return $count;
        }

        return $this->studentAssignments()->where('status', 'enrolled')->count();
    }

    public function hasAvailableCapacity(): bool
    {
        return $this->enrolledCount() < $this->capacity;
    }
}
