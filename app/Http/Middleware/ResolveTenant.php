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
        'sms',
        'portal',
        'dev',
        'test',
        'demo',
        'auth',
        'panel',
        'cpanel',
        'webmail',
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
        // Check if subdomain resolution is explicitly disabled
        if (config('app.resolve_tenant_subdomain', env('TENANT_RESOLVE_SUBDOMAIN', true)) === false) {
            return null;
        }

        $host = strtolower($request->getHost());

        // Skip IP addresses
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return null;
        }

        // Check configured APP_DOMAIN
        $appDomain = strtolower((string) config('app.domain', ''));

        // Check host from configured APP_URL
        $appUrlHost = strtolower((string) parse_url(config('app.url', ''), PHP_URL_HOST));

        // If the current host matches the base application domain or APP_URL host exactly,
        // it is the root application platform, NOT a tenant subdomain.
        if (($appDomain && $host === $appDomain) || ($appUrlHost && $host === $appUrlHost)) {
            return null;
        }

        // Check if host is a subdomain of APP_DOMAIN (e.g. greenwood.sms.abshewabu.dev or greenwood.example.com)
        if ($appDomain && $appDomain !== 'localhost' && str_ends_with($host, '.' . $appDomain)) {
            $prefix = substr($host, 0, -strlen('.' . $appDomain));
            $parts = explode('.', $prefix);
            $candidate = end($parts) ?: null;
            return ($candidate && !in_array($candidate, $this->reservedSubdomains, true)) ? $candidate : null;
        }

        // Check if host is a subdomain of APP_URL host
        if ($appUrlHost && $appUrlHost !== 'localhost' && str_ends_with($host, '.' . $appUrlHost)) {
            $prefix = substr($host, 0, -strlen('.' . $appUrlHost));
            $parts = explode('.', $prefix);
            $candidate = end($parts) ?: null;
            return ($candidate && !in_array($candidate, $this->reservedSubdomains, true)) ? $candidate : null;
        }

        $parts = explode('.', $host);

        // Subdomains on localhost, e.g. greenwood.localhost
        if (count($parts) >= 2 && end($parts) === 'localhost') {
            $candidate = $parts[count($parts) - 2];
            return (!in_array($candidate, $this->reservedSubdomains, true)) ? $candidate : null;
        }

        // Subdomains on standard domains: greenwood.example.com -> parts: ['greenwood', 'example', 'com']
        if (count($parts) >= 3) {
            $candidate = $parts[0];
            if (in_array($candidate, $this->reservedSubdomains, true)) {
                return null;
            }

            // If base domain matches configured appDomain or appUrlHost
            $baseDomain = implode('.', array_slice($parts, 1));
            if ($baseDomain === $appDomain || $baseDomain === $appUrlHost) {
                return $candidate;
            }

            // Fallback for standard 2-level TLDs or localhost defaults
            if (empty($appDomain) || $appDomain === 'localhost') {
                return $candidate;
            }
        }

        return null;
    }
}
