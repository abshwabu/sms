<?php

namespace App\Policies;

use App\Models\User;

abstract class BaseTenantPolicy
{
    /**
     * Perform pre-authorization checks.
     * Platform Super Admins have unrestricted access across all tenants.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return null;
    }

    /**
     * Verify that the user belongs to the same school as the resource.
     */
    protected function isSameSchool(User $user, mixed $model): bool
    {
        $resourceSchoolId = is_object($model) && isset($model->school_id) 
            ? (int) $model->school_id 
            : null;

        if ($resourceSchoolId === null) {
            return false;
        }

        return (int) $user->school_id === $resourceSchoolId;
    }
}
