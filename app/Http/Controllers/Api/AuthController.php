<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Http\Responses\ApiResponse;
use App\Http\Traits\HasApiResponse;
use App\Models\User;
use App\Tenancy\TenantManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    use HasApiResponse;

    public function __construct(
        protected TenantManager $tenantManager
    ) {}

    /**
     * Authenticate user and return token, role, and school context.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();
        $user = User::with('school')->where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return ApiResponse::error(
                'Invalid credentials.',
                'INVALID_CREDENTIALS',
                Response::HTTP_UNAUTHORIZED
            );
        }

        // Check account status
        if ($user->status === UserStatus::INVITED) {
            return ApiResponse::error(
                'Your account has not yet been activated. Please accept your invitation link.',
                'ACCOUNT_INVITED',
                Response::HTTP_FORBIDDEN
            );
        }

        if ($user->status === UserStatus::SUSPENDED) {
            return ApiResponse::error(
                'Your account has been suspended. Please contact your school administrator.',
                'ACCOUNT_SUSPENDED',
                Response::HTTP_FORBIDDEN
            );
        }

        // Cross-tenant verification: if tenant is resolved via header/subdomain, verify membership
        if ($this->tenantManager->hasTenant() && ! $user->isSuperAdmin()) {
            $activeTenant = $this->tenantManager->getTenant();
            if ((int) $user->school_id !== (int) $activeTenant->id) {
                return ApiResponse::error(
                    'Access denied: You do not belong to this school tenant.',
                    'CROSS_TENANT_LOGIN_FORBIDDEN',
                    Response::HTTP_FORBIDDEN
                );
            }
        }

        // Update last login timestamp
        $user->update(['last_login_at' => now()]);

        // Issue Sanctum token
        $deviceName = $credentials['device_name'] ?? 'web-token';
        $token = $user->createToken($deviceName)->plainTextToken;

        // Ensure active tenant context matches user's school if not already set
        if (! $this->tenantManager->hasTenant() && $user->school) {
            $this->tenantManager->setTenant($user->school);
        }

        $schoolContext = $user->school ? [
            'id' => $user->school->id,
            'name' => $user->school->name,
            'subdomain' => $user->school->subdomain,
            'timezone' => $user->school->timezone,
        ] : null;

        return $this->respondWithSuccess([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role,
                'status' => $user->status->value,
                'last_login_at' => $user->last_login_at?->toIso8601String(),
                'school' => $schoolContext,
                'permissions' => $user->getAllPermissions()->pluck('name'),
            ],
        ], 'Login successful.');
    }

    /**
     * Get the authenticated user's profile and active context.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('school');

        $schoolContext = $user->school ? [
            'id' => $user->school->id,
            'name' => $user->school->name,
            'subdomain' => $user->school->subdomain,
            'timezone' => $user->school->timezone,
        ] : null;

        return $this->respondWithSuccess([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $user->role,
            'status' => $user->status->value,
            'last_login_at' => $user->last_login_at?->toIso8601String(),
            'school' => $schoolContext,
            'permissions' => $user->getAllPermissions()->pluck('name'),
        ], 'User profile retrieved.');
    }

    /**
     * Revoke the current access token.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->respondWithSuccess(null, 'Logged out successfully.');
    }

    /**
     * Send password reset link to user.
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $status = Password::sendResetLink($request->only('email'));

        if ($status === Password::RESET_LINK_SENT) {
            return $this->respondWithSuccess(null, 'Password reset link sent.');
        }

        return ApiResponse::error(
            'Unable to send password reset link.',
            'PASSWORD_RESET_FAILED',
            Response::HTTP_BAD_REQUEST
        );
    }

    /**
     * Reset the user's password using token.
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->save();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return $this->respondWithSuccess(null, 'Password has been reset successfully.');
        }

        return ApiResponse::error(
            'Unable to reset password with provided token.',
            'PASSWORD_RESET_FAILED',
            Response::HTTP_BAD_REQUEST
        );
    }
}
