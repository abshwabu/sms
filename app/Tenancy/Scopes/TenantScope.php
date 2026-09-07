<?php

namespace App\Tenancy\Scopes;

use App\Tenancy\Exceptions\TenantContextRequiredException;
use App\Tenancy\TenantManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * @throws TenantContextRequiredException
     */
    public function apply(Builder $builder, Model $model): void
    {
        $tenantManager = app(TenantManager::class);

        if ($tenantManager->isBypassed()) {
            return;
        }

        if (! $tenantManager->hasTenant()) {
            throw new TenantContextRequiredException(
                sprintf('Cannot query tenant-scoped model [%s] without an active tenant context.', get_class($model))
            );
        }

        $builder->where($model->qualifyColumn('school_id'), '=', $tenantManager->getTenantId());
    }

    /**
     * Extend the query builder with tenant-related macros.
     */
    public function extend(Builder $builder): void
    {
        $builder->macro('withoutTenantScope', function (Builder $builder) {
            return $builder->withoutGlobalScope($this);
        });
    }
}
