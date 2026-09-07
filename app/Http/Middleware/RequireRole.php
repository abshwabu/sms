<?php

namespace App\Http\Middleware;

use App\Http\Responses\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$roles
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return ApiResponse::error('Unauthenticated.', 'UNAUTHENTICATED', Response::HTTP_UNAUTHORIZED);
        }

        // Super Admin has global bypass
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        $hasRole = in_array($user->role, $roles, true) || $user->hasAnyRole($roles);

        if (! $hasRole) {
            return ApiResponse::error(
                'Forbidden: You do not have the required role to access this resource.',
                'FORBIDDEN',
                Response::HTTP_FORBIDDEN
            );
        }

        return $next($request);
    }
}
