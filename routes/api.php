<?php

use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\SchoolController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Tenant context is resolved automatically by the ResolveTenant middleware
| from either the subdomain or the X-School-Id header.
|
*/

// Health check endpoint (reports status + resolved tenant context if provided)
Route::get('/health', HealthController::class)->name('api.health');

// Schools list and details (platform level)
Route::get('/schools', [SchoolController::class, 'index'])->name('api.schools.index');
Route::get('/schools/{school}', [SchoolController::class, 'show'])->name('api.schools.show');

// Tenant-scoped routes (strict tenant context enforced)
Route::middleware('tenant.require')->group(function () {
    Route::get('/tenant/health', HealthController::class)->name('api.tenant.health');
    Route::apiResource('courses', CourseController::class);
});

// Authenticated user (Sanctum)
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
