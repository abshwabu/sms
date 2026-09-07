<?php

namespace App\Http\Middleware;

use App\Http\Responses\ApiResponse;
use App\Tenancy\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserBelongsToTenant
{
    public function __construct(
        protected TenantManager $tenantManager
    ) {}

    /**
     * Handle an incoming request.
     * Ensure the authenticated user has access to the resolved tenant.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $this->tenantManager->hasTenant()) {
            $currentTenant = $this->tenantManager->getTenant();

            if (! $user->belongsToSchool($currentTenant->id)) {
                return ApiResponse::error(
                    'Access denied: You do not have permission to access resources belonging to this school tenant.',
                    'FORBIDDEN_TENANT_ACCESS',
                    Response::HTTP_FORBIDDEN
                );
            }
        }

        return $next($request);
    }
}
