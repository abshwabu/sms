<?php

namespace App\Http\Middleware;

use App\Models\School;
use App\Tenancy\Exceptions\TenantContextRequiredException;
use App\Tenancy\Exceptions\TenantNotFoundException;
use App\Tenancy\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    /**
     * Reserved subdomains that shouldn't be treated as tenant identifiers.
     *
     * @var array<int, string>
     */
    protected array $reservedSubdomains = [
        'www',
        'api',
        'admin',
        'app',
        'mail',
        'staging',
        'localhost',
    ];

    public function __construct(
        protected TenantManager $tenantManager
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string|null  $mode  'required' or 'optional'
     */
    public function handle(Request $request, Closure $next, ?string $mode = null): Response
    {
        // Reset tenant state for the current request
        $this->tenantManager->clearTenant();

        $school = $this->resolveFromHeader($request) ?? $this->resolveFromSubdomain($request);

        if ($school) {
            $this->tenantManager->setTenant($school);
        } elseif ($mode === 'required') {
            throw new TenantContextRequiredException('School tenant context is required. Provide a valid subdomain or X-School-Id header.');
        }

        return $next($request);
    }

    /**
     * Resolve school tenant from X-School-Id header.
     */
    protected function resolveFromHeader(Request $request): ?School
    {
        $headerValue = $request->header('X-School-Id');

        if (! $headerValue) {
            return null;
        }

        $school = is_numeric($headerValue)
            ? School::find((int) $headerValue)
            : School::where('subdomain', $headerValue)->first();

        if (! $school) {
            throw new TenantNotFoundException("School tenant with identifier '{$headerValue}' was not found.");
        }

        return $school;
    }

    /**
     * Resolve school tenant from request host subdomain.
     */
    protected function resolveFromSubdomain(Request $request): ?School
    {
        $subdomain = $this->extractSubdomain($request);

        if (! $subdomain || in_array(strtolower($subdomain), $this->reservedSubdomains, true)) {
            return null;
        }

        $school = School::where('subdomain', $subdomain)->first();

        if (! $school) {
            throw new TenantNotFoundException("School tenant with subdomain '{$subdomain}' was not found.");
        }

        return $school;
    }

    /**
     * Extract the subdomain from the request host.
     */
    protected function extractSubdomain(Request $request): ?string
    {
        $host = $request->getHost();

        // Skip IP addresses
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return null;
        }

        // Check configured APP_DOMAIN if available
        $appDomain = config('app.domain');
        if ($appDomain && str_ends_with($host, '.' . $appDomain)) {
            $prefix = substr($host, 0, -strlen('.' . $appDomain));
            $parts = explode('.', $prefix);
            return end($parts) ?: null;
        }

        $parts = explode('.', $host);

        // Subdomains on localhost, e.g. greenwood.localhost
        if (count($parts) >= 2 && end($parts) === 'localhost') {
            return $parts[count($parts) - 2];
        }

        // Subdomains on standard domain: greenwood.example.com -> parts: ['greenwood', 'example', 'com']
        if (count($parts) >= 3) {
            return $parts[0];
        }

        return null;
    }
}
