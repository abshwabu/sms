<?php

namespace App\Tenancy\Traits;

use App\Models\School;
use App\Tenancy\Exceptions\TenantContextRequiredException;
use App\Tenancy\Scopes\TenantScope;
use App\Tenancy\TenantManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait TenantScoped
{
    /**
     * Boot the tenant scoped trait for a model.
     */
    public static function bootTenantScoped(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($model) {
            $tenantManager = app(TenantManager::class);

            if (empty($model->school_id)) {
                if ($tenantManager->hasTenant()) {
                    $model->school_id = $tenantManager->getTenantId();
                } elseif (! $tenantManager->isBypassed()) {
                    throw new TenantContextRequiredException(
                        sprintf('Cannot create tenant model [%s] without an active tenant context.', static::class)
                    );
                }
            }
        });
    }

    /**
     * Get the school that owns this model.
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class, 'school_id');
    }

    /**
     * Scope query to explicitly target a specific school.
     */
    public function scopeForSchool(Builder $query, int $schoolId): Builder
    {
        return $query->withoutGlobalScope(TenantScope::class)->where('school_id', $schoolId);
    }
}
