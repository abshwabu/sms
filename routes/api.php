<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClaimCodeController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\InvitationController;
use App\Http\Controllers\Api\SchoolAdminController;
use App\Http\Controllers\Api\SchoolController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Dual-channel tenant resolution (subdomain or X-School-Id) is prepended
| globally to the api middleware stack.
|
*/

// Platform & Public Endpoints
Route::get('/health', HealthController::class)->name('api.health');
Route::middleware('tenant.require')->get('/tenant/health', HealthController::class)->name('api.tenant.health');
Route::get('/schools', [SchoolController::class, 'index'])->name('api.schools.index');
Route::get('/schools/{school}', [SchoolController::class, 'show'])->name('api.schools.show');

// Public Authentication Endpoints
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->name('api.auth.login');
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->name('api.auth.forgot-password');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('api.auth.reset-password');
});

// Public Onboarding Endpoints
Route::post('/invitations/accept', [InvitationController::class, 'accept'])->name('api.invitations.accept');
Route::post('/claim-codes/claim', [ClaimCodeController::class, 'claim'])->name('api.claim-codes.claim');

// Authenticated Routes (Sanctum)
Route::middleware('auth:sanctum')->group(function () {
    // Current user profile & session
    Route::get('/auth/me', [AuthController::class, 'me'])->name('api.auth.me');
    Route::post('/auth/logout', [AuthController::class, 'logout'])->name('api.auth.logout');

    // Tenant-Scoped Routes: requires active tenant AND verifies user belongs to this tenant
    Route::middleware(['tenant.require', 'tenant.user'])->group(function () {
        Route::apiResource('courses', CourseController::class);

        // School Admin Only Endpoints (403 for teacher/student/parent)
        Route::middleware('role:school_admin,super_admin')->prefix('admin')->group(function () {
            Route::get('/users', [SchoolAdminController::class, 'users'])->name('api.admin.users');
            Route::get('/stats', [SchoolAdminController::class, 'stats'])->name('api.admin.stats');
            Route::post('/invitations', [InvitationController::class, 'invite'])->name('api.admin.invitations');
            Route::post('/claim-codes', [ClaimCodeController::class, 'generate'])->name('api.admin.claim-codes');
        });
    });
});
