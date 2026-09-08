<?php

namespace App\Providers;

use App\Models\AttendanceRecord;
use App\Models\ParentProfile;
use App\Policies\AttendancePolicy;
use App\Policies\ParentProfilePolicy;
use App\Tenancy\TenantManager;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TenantManager::class, function () {
            return new TenantManager();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(ParentProfile::class, ParentProfilePolicy::class);
        Gate::policy(AttendanceRecord::class, AttendancePolicy::class);
        Gate::policy(\App\Models\ReportCard::class, \App\Policies\ReportCardPolicy::class);
        Gate::policy(\App\Models\Grade::class, \App\Policies\GradePolicy::class);
        Gate::policy(\App\Models\TimetableSlot::class, \App\Policies\TimetableSlotPolicy::class);
        Route::model('parent', ParentProfile::class);
    }
}
