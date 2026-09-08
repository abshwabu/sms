<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\Section;
use App\Models\User;

class SectionPolicy extends BaseTenantPolicy
{
    /**
     * Determine whether the user can view any sections.
     */
    public function viewAny(User $user): bool
    {
        return $user->isSchoolAdmin() || $user->isTeacher() || $user->isStudent();
    }

    /**
     * Determine whether the user can view section details.
     * Acceptance criterion: A teacher assigned as homeroom can see that section;
     * cannot see other sections unless separately assigned a subject there.
     */
    public function view(User $user, Section $section): bool
    {
        if (! $this->isSameSchool($user, $section)) {
            return false;
        }

        if ($user->isSchoolAdmin()) {
            return true;
        }

        if ($user->isTeacher()) {
            return $section->hasTeacher($user);
        }

        if ($user->isStudent()) {
            return (int) $user->student?->current_section_id === (int) $section->id;
        }

        return false;
    }

    /**
     * Determine whether the user can view the section roster.
     */
    public function viewRoster(User $user, Section $section): bool
    {
        if (! $this->isSameSchool($user, $section)) {
            return false;
        }

        if ($user->isSchoolAdmin()) {
            return true;
        }

        if ($user->isTeacher()) {
            return $section->hasTeacher($user);
        }

        return false;
    }

    /**
     * Determine whether the user can record or update attendance for this section.
     */
    public function recordAttendance(User $user, Section $section): bool
    {
        if (! $this->isSameSchool($user, $section)) {
            return false;
        }

        if ($user->isSchoolAdmin()) {
            return true;
        }

        if ($user->isTeacher()) {
            return $section->canTeacherTakeAttendance($user);
        }

        return false;
    }

    /**
     * Determine whether the user can record or update grades for a course in this section.
     */
    public function recordGrades(User $user, Section $section, ?Course $course = null): bool
    {
        if (! $this->isSameSchool($user, $section)) {
            return false;
        }

        if ($user->isSchoolAdmin()) {
            return true;
        }

        if ($user->isTeacher()) {
            return $section->canTeacherGrade($user, $course?->id);
        }

        return false;
    }

    /**
     * Determine whether the user can create sections.
     */
    public function create(User $user): bool
    {
        return $user->isSchoolAdmin();
    }

    /**
     * Determine whether the user can update sections.
     */
    public function update(User $user, Section $section): bool
    {
        if (! $this->isSameSchool($user, $section)) {
            return false;
        }

        return $user->isSchoolAdmin();
    }

    /**
     * Determine whether the user can delete sections.
     */
    public function delete(User $user, Section $section): bool
    {
        if (! $this->isSameSchool($user, $section)) {
            return false;
        }

        return $user->isSchoolAdmin();
    }

    /**
     * Determine whether the user can assign teachers to this section.
     */
    public function assignTeacher(User $user, Section $section): bool
    {
        if (! $this->isSameSchool($user, $section)) {
            return false;
        }

        return $user->isSchoolAdmin();
    }
}
