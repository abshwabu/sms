<?php

namespace App\Policies;

use App\Models\ParentProfile;
use App\Models\User;

class ParentProfilePolicy extends BaseTenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSchoolAdmin() || $user->isTeacher();
    }

    public function view(User $user, ParentProfile $parent): bool
    {
        if (! $this->isSameSchool($user, $parent)) {
            return false;
        }

        if ($user->isSchoolAdmin() || $user->isTeacher()) {
            return true;
        }

        return (int) $user->id === (int) $parent->user_id;
    }

    public function create(User $user): bool
    {
        return $user->isSchoolAdmin();
    }

    public function update(User $user, ParentProfile $parent): bool
    {
        if (! $this->isSameSchool($user, $parent)) {
            return false;
        }

        return $user->isSchoolAdmin() || (int) $user->id === (int) $parent->user_id;
    }

    public function delete(User $user, ParentProfile $parent): bool
    {
        if (! $this->isSameSchool($user, $parent)) {
            return false;
        }

        return $user->isSchoolAdmin();
    }

    public function linkStudent(User $user, ParentProfile $parent): bool
    {
        if (! $this->isSameSchool($user, $parent)) {
            return false;
        }

        return $user->isSchoolAdmin();
    }
}
