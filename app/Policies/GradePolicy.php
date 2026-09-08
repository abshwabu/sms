<?php

namespace App\Policies;

use App\Models\Grade;
use App\Models\User;

class GradePolicy extends BaseTenantPolicy
{
    /**
     * Determine whether the user can view grade listings.
     */
    public function viewAny(User $user): bool
    {
        return $user->isSchoolAdmin() || $user->isTeacher() || $user->isStudent() || $user->isParent();
    }

    /**
     * Determine whether the user can view a specific grade.
     */
    public function view(User $user, Grade $grade): bool
    {
        if (! $this->isSameSchool($user, $grade)) {
            return false;
        }

        if ($user->isSchoolAdmin() || $user->isTeacher()) {
            return true;
        }

        if ($user->isStudent()) {
            return $user->student && (int) $user->student->id === (int) $grade->student_id;
        }

        if ($user->isParent()) {
            return $user->parentProfile && $user->parentProfile->isLinkedTo($grade->student_id);
        }

        return false;
    }

    /**
     * Determine whether the user can record grades.
     */
    public function create(User $user): bool
    {
        return $user->isSchoolAdmin() || $user->isTeacher();
    }

    /**
     * Determine whether the user can update a grade.
     */
    public function update(User $user, Grade $grade): bool
    {
        if (! $this->isSameSchool($user, $grade)) {
            return false;
        }

        if ($user->isSchoolAdmin()) {
            return true;
        }

        if ($user->isTeacher()) {
            // Can update if entered by them, or if homeroom teacher
            if ($grade->entered_by === $user->id) {
                return true;
            }

            if ($grade->section && $grade->section->isHomeroomTeacher($user)) {
                return true;
            }

            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete a grade.
     */
    public function delete(User $user, Grade $grade): bool
    {
        if (! $this->isSameSchool($user, $grade)) {
            return false;
        }

        return $user->isSchoolAdmin();
    }
}
