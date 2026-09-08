<?php

namespace App\Models;

use App\Tenancy\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Staff extends Model
{
    use HasFactory, TenantScoped;

    protected $table = 'staff';

    protected $fillable = [
        'school_id',
        'user_id',
        'staff_number',
        'role_title',
        'department',
        'hire_date',
        'status',
        'phone',
        'qualification',
        'subjects_taught',
    ];

    protected function casts(): array
    {
        return [
            'hire_date' => 'date',
            'subjects_taught' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'course_staff')
            ->withTimestamps();
    }

    public function sectionSubjectAssignments(): HasMany
    {
        return $this->hasMany(SectionSubjectTeacher::class, 'staff_id');
    }

    public function homeroomSections(): HasMany
    {
        return $this->hasMany(Section::class, 'homeroom_teacher_id', 'user_id');
    }

    public function subjectSections(): BelongsToMany
    {
        return $this->belongsToMany(Section::class, 'section_subject_teachers', 'staff_id', 'section_id')
            ->withPivot(['course_id', 'academic_year_id'])
            ->withTimestamps();
    }

    public function bookLoans(): HasMany
    {
        return $this->hasMany(BookLoan::class)->orderBy('borrowed_at', 'desc');
    }

    public function activeBookLoans(): HasMany
    {
        return $this->hasMany(BookLoan::class)->whereNull('returned_at')->orderBy('due_at', 'asc');
    }

    /**
     * Get all unique sections assigned to this staff member (as homeroom or subject teacher).
     */
    public function allAssignedSections(): Collection
    {
        $homeroom = $this->homeroomSections()->get();
        $subjectSections = $this->subjectSections()->get();

        return $homeroom->merge($subjectSections)->unique('id');
    }

    /**
     * Determine if this staff member is assigned as homeroom teacher for a given section.
     */
    public function isHomeroomFor(int|Section $section): bool
    {
        $sectionId = $section instanceof Section ? $section->id : $section;
        return $this->homeroomSections()->where('id', $sectionId)->exists();
    }

    /**
     * Determine if this staff member teaches in a given section.
     */
    public function teachesSection(int|Section $section): bool
    {
        $sectionId = $section instanceof Section ? $section->id : $section;

        if ($this->isHomeroomFor($sectionId)) {
            return true;
        }

        return $this->sectionSubjectAssignments()->where('section_id', $sectionId)->exists();
    }

    /**
     * Determine if this staff member has grading permissions for a subject in a section.
     */
    public function canGradeSubject(int|Section $section, int|Course|null $course = null): bool
    {
        $sectionId = $section instanceof Section ? $section->id : $section;

        // Homeroom teacher has master grading oversight for their own section
        if ($this->isHomeroomFor($sectionId)) {
            return true;
        }

        // If specific course requested, verify subject teacher assignment
        if ($course) {
            $courseId = $course instanceof Course ? $course->id : $course;
            return $this->sectionSubjectAssignments()
                ->where('section_id', $sectionId)
                ->where('course_id', $courseId)
                ->exists();
        }

        // If no course specified, check if assigned any subject in this section
        return $this->sectionSubjectAssignments()
            ->where('section_id', $sectionId)
            ->exists();
    }

    /**
     * Scope search query across staff number, role title, department, or user name/email.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (empty($term)) {
            return $query;
        }

        return $query->where(function ($q) use ($term) {
            $q->where('staff_number', 'like', "%{$term}%")
              ->orWhere('role_title', 'like', "%{$term}%")
              ->orWhere('department', 'like', "%{$term}%")
              ->orWhereHas('user', function ($uq) use ($term) {
                  $uq->where('name', 'like', "%{$term}%")
                     ->orWhere('email', 'like', "%{$term}%");
              });
        });
    }

    /**
     * Scope query by status.
     */
    public function scopeWithStatus(Builder $query, ?string $status): Builder
    {
        if (empty($status)) {
            return $query;
        }

        return $query->where('status', $status);
    }

    /**
     * Scope query by department.
     */
    public function scopeInDepartment(Builder $query, ?string $department): Builder
    {
        if (empty($department)) {
            return $query;
        }

        return $query->where('department', $department);
    }

    /**
     * Scope query by role title.
     */
    public function scopeWithRole(Builder $query, ?string $roleTitle): Builder
    {
        if (empty($roleTitle)) {
            return $query;
        }

        return $query->where('role_title', $roleTitle);
    }
}
