<?php

namespace App\Tenancy;

use Spatie\Permission\DefaultTeamResolver;

class SchoolTeamResolver extends DefaultTeamResolver
{
    /**
     * Get the permissions team (school) ID.
     * Returns 0 for platform/global roles (super_admin) where school_id is null.
     */
    public function getPermissionsTeamId(): int|string|null
    {
        if ($this->teamId !== null) {
            return $this->teamId;
        }

        $tenantManager = app(TenantManager::class);
        if ($tenantManager->hasTenant()) {
            return $tenantManager->getTenantId() ?? 0;
        }

        $user = auth()->user();
        if ($user && $user->school_id !== null) {
            return $user->school_id;
        }

        return 0;
    }
}
