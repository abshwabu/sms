<?php

namespace App\Policies;

use App\Models\SubjectOffering;
use App\Models\User;

class SubjectOfferingPolicy extends BaseTenantPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, SubjectOffering $subjectOffering): bool
    {
        return $this->isSameSchool($user, $subjectOffering);
    }

    public function create(User $user): bool
    {
        return $user->isSchoolAdmin();
    }

    public function update(User $user, SubjectOffering $subjectOffering): bool
    {
        return $this->isSameSchool($user, $subjectOffering) && $user->isSchoolAdmin();
    }

    public function delete(User $user, SubjectOffering $subjectOffering): bool
    {
        return $this->isSameSchool($user, $subjectOffering) && $user->isSchoolAdmin();
    }
}
