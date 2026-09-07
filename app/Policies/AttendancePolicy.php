<?php

namespace App\Policies;

use App\Models\User;

class AttendancePolicy extends BaseTenantPolicy
{
    /**
     * Determine whether the user can view attendance listings.
     */
    public function viewAny(User $user): bool
    {
        return $user->isSchoolAdmin() || $user->isTeacher();
    }

    /**
     * Determine whether the user can view a specific attendance record.
     */
    public function view(User $user, mixed $attendance): bool
    {
        if (! $this->isSameSchool($user, $attendance)) {
            return false;
        }

        if ($user->isSchoolAdmin() || $user->isTeacher()) {
            return true;
        }

        // Student can view their own attendance
        if ($user->isStudent() && isset($attendance->student_id) && $user->id === (int) $attendance->student_id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can record attendance.
     */
    public function create(User $user): bool
    {
        return $user->isSchoolAdmin() || $user->isTeacher();
    }

    /**
     * Determine whether the user can update attendance.
     */
    public function update(User $user, mixed $attendance): bool
    {
        if (! $this->isSameSchool($user, $attendance)) {
            return false;
        }

        return $user->isSchoolAdmin() || $user->isTeacher();
    }

    /**
     * Determine whether the user can delete attendance records.
     */
    public function delete(User $user, mixed $attendance): bool
    {
        if (! $this->isSameSchool($user, $attendance)) {
            return false;
        }

        // Only school admins can purge attendance records
        return $user->isSchoolAdmin();
    }
}
