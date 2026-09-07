<?php

namespace App\Http\Middleware;

use App\Tenancy\Exceptions\TenantContextRequiredException;
use App\Tenancy\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireTenant
{
    public function __construct(
        protected TenantManager $tenantManager
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->tenantManager->hasTenant()) {
            throw new TenantContextRequiredException('School tenant context is required for this action. Provide X-School-Id header or access via school subdomain.');
        }

        return $next($request);
    }
}
