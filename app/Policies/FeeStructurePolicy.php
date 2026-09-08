<?php

namespace App\Policies;

use App\Models\FeeStructure;
use App\Models\User;

class FeeStructurePolicy extends BaseTenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSchoolAdmin();
    }

    public function view(User $user, FeeStructure $feeStructure): bool
    {
        return $this->isSameSchool($user, $feeStructure) && $user->isSchoolAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isSchoolAdmin();
    }

    public function update(User $user, FeeStructure $feeStructure): bool
    {
        return $this->isSameSchool($user, $feeStructure) && $user->isSchoolAdmin();
    }

    public function delete(User $user, FeeStructure $feeStructure): bool
    {
        return $this->isSameSchool($user, $feeStructure) && $user->isSchoolAdmin();
    }
}
