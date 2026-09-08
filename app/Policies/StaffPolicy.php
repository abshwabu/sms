<?php

namespace App\Policies;

use App\Models\Staff;
use App\Models\User;

class StaffPolicy extends BaseTenantPolicy
{
    /**
     * Determine whether the user can view any staff records.
     */
    public function viewAny(User $user): bool
    {
        return $user->isSchoolAdmin() || $user->isTeacher();
    }

    /**
     * Determine whether the user can view the specified staff record.
     */
    public function view(User $user, Staff $staff): bool
    {
        if (! $this->isSameSchool($user, $staff)) {
            return false;
        }

        if ($user->isSchoolAdmin() || $user->isTeacher()) {
            return true;
        }

        return (int) $user->id === (int) $staff->user_id;
    }

    /**
     * Determine whether the user can create staff records.
     */
    public function create(User $user): bool
    {
        return $user->isSchoolAdmin();
    }

    /**
     * Determine whether the user can update staff records.
     */
    public function update(User $user, Staff $staff): bool
    {
        if (! $this->isSameSchool($user, $staff)) {
            return false;
        }

        return $user->isSchoolAdmin();
    }

    /**
     * Determine whether the user can delete staff records.
     */
    public function delete(User $user, Staff $staff): bool
    {
        if (! $this->isSameSchool($user, $staff)) {
            return false;
        }

        return $user->isSchoolAdmin();
    }
}
