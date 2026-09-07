<?php

use App\Http\Controllers\Api\AcademicYearController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClaimCodeController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\GradeLevelController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\InvitationController;
use App\Http\Controllers\Api\SchoolAdminController;
use App\Http\Controllers\Api\SchoolController;
use App\Http\Controllers\Api\SectionController;
use App\Http\Controllers\Api\TermController;
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

        // Academic Structure Queries (Read)
        Route::get('/academic-years', [AcademicYearController::class, 'index'])->name('api.academic-years.index');
        Route::get('/academic-years/{academicYear}', [AcademicYearController::class, 'show'])->name('api.academic-years.show');
        Route::get('/academic-years/{academicYear}/terms', [TermController::class, 'index'])->name('api.academic-years.terms.index');
        Route::get('/grade-levels', [GradeLevelController::class, 'index'])->name('api.grade-levels.index');
        Route::get('/grade-levels/{gradeLevel}', [GradeLevelController::class, 'show'])->name('api.grade-levels.show');
        Route::get('/sections', [SectionController::class, 'index'])->name('api.sections.index');
        Route::get('/sections/{section}', [SectionController::class, 'show'])->name('api.sections.show');

        // School Admin Only Endpoints (403 for teacher/student/parent)
        Route::middleware('role:school_admin,super_admin')->group(function () {
            // School admin management
            Route::prefix('admin')->group(function () {
                Route::get('/users', [SchoolAdminController::class, 'users'])->name('api.admin.users');
                Route::get('/stats', [SchoolAdminController::class, 'stats'])->name('api.admin.stats');
                Route::post('/invitations', [InvitationController::class, 'invite'])->name('api.admin.invitations');
                Route::post('/claim-codes', [ClaimCodeController::class, 'generate'])->name('api.admin.claim-codes');
            });

            // Academic Structure Management (Write)
            Route::post('/academic-years', [AcademicYearController::class, 'store'])->name('api.academic-years.store');
            Route::put('/academic-years/{academicYear}', [AcademicYearController::class, 'update'])->name('api.academic-years.update');
            Route::post('/academic-years/{academicYear}/close', [AcademicYearController::class, 'close'])->name('api.academic-years.close');
            Route::post('/academic-years/{academicYear}/activate', [AcademicYearController::class, 'activate'])->name('api.academic-years.activate');

            Route::post('/academic-years/{academicYear}/terms', [TermController::class, 'store'])->name('api.academic-years.terms.store');
            Route::put('/terms/{term}', [TermController::class, 'update'])->name('api.terms.update');
            Route::delete('/terms/{term}', [TermController::class, 'destroy'])->name('api.terms.destroy');

            Route::post('/grade-levels', [GradeLevelController::class, 'store'])->name('api.grade-levels.store');
            Route::put('/grade-levels/{gradeLevel}', [GradeLevelController::class, 'update'])->name('api.grade-levels.update');
            Route::delete('/grade-levels/{gradeLevel}', [GradeLevelController::class, 'destroy'])->name('api.grade-levels.destroy');

            Route::post('/sections', [SectionController::class, 'store'])->name('api.sections.store');
            Route::put('/sections/{section}', [SectionController::class, 'update'])->name('api.sections.update');
            Route::delete('/sections/{section}', [SectionController::class, 'destroy'])->name('api.sections.destroy');
            Route::post('/sections/{section}/assign-student', [SectionController::class, 'assignStudent'])->name('api.sections.assign-student');
            Route::post('/sections/promote', [SectionController::class, 'promote'])->name('api.sections.promote');
        });
    });
});
