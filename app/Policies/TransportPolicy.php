<?php

namespace App\Policies;

use App\Models\Student;
use App\Models\User;

class TransportPolicy extends BaseTenantPolicy
{
    /**
     * Determine whether the user can view transport routes.
     */
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin()
            || $user->isSchoolAdmin()
            || $user->isTeacher()
            || $user->isStudent()
            || $user->isParent();
    }

    /**
     * Determine whether the user can create, update, or delete routes and assign students.
     */
    public function manage(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isSchoolAdmin();
    }

    /**
     * Determine whether the user can view a specific student's transport details.
     */
    public function viewStudentTransport(User $user, Student $student): bool
    {
        if (! $this->isSameSchool($user, $student)) {
            return false;
        }

        if ($user->isSuperAdmin() || $user->isSchoolAdmin()) {
            return true;
        }

        if ($user->isStudent() && $user->student && (int) $user->student->id === (int) $student->id) {
            return true;
        }

        if ($user->isParent() && $user->parentProfile) {
            return $user->parentProfile->students()->where('students.id', $student->id)->exists();
        }

        return false;
    }
}
