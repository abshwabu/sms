<?php

namespace App\Policies;

use App\Models\Student;
use App\Models\User;

class StudentPolicy extends BaseTenantPolicy
{
    /**
     * Determine whether the user can view any student records.
     */
    public function viewAny(User $user): bool
    {
        // School admins and teachers can view all student records in their school
        return $user->isSchoolAdmin() || $user->isTeacher();
    }

    /**
     * Determine whether the user can view the specific student record.
     */
    public function view(User $user, Student|User $student): bool
    {
        if (! $this->isSameSchool($user, $student)) {
            return false;
        }

        // School admin and teacher can view any student in the same school
        if ($user->isSchoolAdmin() || $user->isTeacher()) {
            return true;
        }

        // Student can view their own record
        $studentUserId = $student instanceof Student ? $student->user_id : $student->id;
        if ($user->isStudent() && $user->id === $studentUserId) {
            return true;
        }

        // Parent can view linked student record
        if ($user->isParent()) {
            $studentId = $student instanceof Student ? $student->id : $student->student?->id;
            if (! $studentId) {
                return false;
            }
            return $user->parentProfile?->isLinkedTo($studentId) ?? false;
        }

        return false;
    }

    /**
     * Determine whether the user can create student accounts.
     */
    public function create(User $user): bool
    {
        // Only school admins can create student accounts
        return $user->isSchoolAdmin();
    }

    /**
     * Determine whether the user can update student records.
     */
    public function update(User $user, Student|User $student): bool
    {
        if (! $this->isSameSchool($user, $student)) {
            return false;
        }

        return $user->isSchoolAdmin() || $user->isTeacher();
    }

    /**
     * Determine whether the user can delete student records.
     */
    public function delete(User $user, Student|User $student): bool
    {
        if (! $this->isSameSchool($user, $student)) {
            return false;
        }

        return $user->isSchoolAdmin();
    }

    /**
     * Determine whether the user can bulk import students.
     */
    public function import(User $user): bool
    {
        return $user->isSchoolAdmin();
    }

    /**
     * Determine whether the user can promote rosters.
     */
    public function promote(User $user): bool
    {
        return $user->isSchoolAdmin();
    }
}
