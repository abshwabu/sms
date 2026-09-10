<?php

namespace App\Providers;

use App\Models\AttendanceRecord;
use App\Models\ParentProfile;
use App\Policies\AttendancePolicy;
use App\Policies\ParentProfilePolicy;
use App\Tenancy\TenantManager;
use Illuminate\Auth\Notifications\ResetPassword;
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

        $this->app->bind(
            \App\Services\Payments\PaymentGatewayInterface::class,
            \App\Services\Payments\ChapaPaymentGateway::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        ResetPassword::createUrlUsing(function ($notifiable, string $token) {
            return url('/login?token=' . $token . '&email=' . urlencode($notifiable->getEmailForPasswordReset()));
        });

        Gate::policy(ParentProfile::class, ParentProfilePolicy::class);
        Gate::policy(AttendanceRecord::class, AttendancePolicy::class);
        Gate::policy(\App\Models\ReportCard::class, \App\Policies\ReportCardPolicy::class);
        Gate::policy(\App\Models\Grade::class, \App\Policies\GradePolicy::class);
        Gate::policy(\App\Models\TimetableSlot::class, \App\Policies\TimetableSlotPolicy::class);
        Gate::policy(\App\Models\Book::class, \App\Policies\LibraryPolicy::class);
        Gate::policy(\App\Models\BookLoan::class, \App\Policies\LibraryPolicy::class);
        Gate::policy(\App\Models\LibraryFine::class, \App\Policies\LibraryPolicy::class);
        Gate::policy(\App\Models\TransportRoute::class, \App\Policies\TransportPolicy::class);
        Gate::policy(\App\Models\TransportStop::class, \App\Policies\TransportPolicy::class);
        Gate::policy(\App\Models\StudentTransport::class, \App\Policies\TransportPolicy::class);
        Gate::policy(\App\Models\Announcement::class, \App\Policies\AnnouncementPolicy::class);
        Gate::policy(\App\Models\CommunicationThread::class, \App\Policies\CommunicationThreadPolicy::class);
        Gate::policy(\App\Models\FeeStructure::class, \App\Policies\FeeStructurePolicy::class);
        Gate::policy(\App\Models\Invoice::class, \App\Policies\InvoicePolicy::class);
        Gate::policy(\App\Models\Payment::class, \App\Policies\PaymentPolicy::class);
        Route::model('parent', ParentProfile::class);
    }
}
