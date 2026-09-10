<?php

namespace App\Mail\Concerns;

use App\Models\School;
use App\Tenancy\TenantManager;

trait ScopesTenantForMail
{
    /**
     * Ensure the tenant context is restored before building, rendering, or resolving mailable data.
     */
    protected function ensureTenantContext(?int $schoolId): void
    {
        if (! $schoolId) {
            return;
        }

        $tenantManager = app(TenantManager::class);
        if (! $tenantManager->hasTenant() || $tenantManager->getTenantId() !== $schoolId) {
            $school = School::withoutGlobalScopes()->find($schoolId);
            if ($school) {
                $tenantManager->setTenant($school);
            }
        }
    }
}
