<?php

namespace App\Tenancy;

use App\Models\School;
use Closure;

class TenantManager
{
    /**
     * The active tenant instance.
     */
    protected ?School $tenant = null;

    /**
     * Flag indicating whether tenant scoping is temporarily bypassed.
     */
    protected bool $bypassScoping = false;

    /**
     * Set the current tenant.
     */
    public function setTenant(?School $tenant): void
    {
        $this->tenant = $tenant;
    }

    /**
     * Get the current active tenant.
     */
    public function getTenant(): ?School
    {
        return $this->tenant;
    }

    /**
     * Get the current active tenant ID.
     */
    public function getTenantId(): ?int
    {
        return $this->tenant?->id;
    }

    /**
     * Check if a tenant is currently resolved.
     */
    public function hasTenant(): bool
    {
        return $this->tenant !== null;
    }

    /**
     * Clear the current tenant context.
     */
    public function clearTenant(): void
    {
        $this->tenant = null;
    }

    /**
     * Check if tenant scoping is bypassed.
     */
    public function isBypassed(): bool
    {
        return $this->bypassScoping;
    }

    public function bypass(callable $callback): mixed
    {
        return $this->bypassTenantScoping($callback);
    }

    public function bypassTenantScoping(callable $callback): mixed
    {
        $previous = $this->bypassScoping;
        $this->bypassScoping = true;

        try {
            return $callback();
        } finally {
            $this->bypassScoping = $previous;
        }
    }
}
