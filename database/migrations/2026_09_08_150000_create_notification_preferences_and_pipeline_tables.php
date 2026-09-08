<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Unified Cross-Cutting Notifications Table
        if (! Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) {
                $table->id();
                $table->foreignId('school_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('type'); // attendance_absence, report_card_published, new_grade, announcement, overdue_book, message_received
                $table->string('title');
                $table->text('body');
                $table->json('payload')->nullable();
                $table->timestamp('read_at')->nullable();
                $table->timestamps();

                $table->index(['school_id', 'user_id', 'read_at']);
                $table->index(['user_id', 'created_at']);
            });
        }

        // 2. User Notification Preferences Table
        if (! Schema::hasTable('notification_preferences')) {
            Schema::create('notification_preferences', function (Blueprint $table) {
                $table->id();
                $table->foreignId('school_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->boolean('email_enabled')->default(true);
                $table->boolean('telegram_enabled')->default(true);
                $table->boolean('sms_enabled')->default(false);
                $table->boolean('push_enabled')->default(false);
                $table->boolean('attendance_alerts')->default(true);
                $table->boolean('grade_alerts')->default(true);
                $table->boolean('announcement_alerts')->default(true);
                $table->boolean('library_alerts')->default(true);
                $table->boolean('message_alerts')->default(true);
                $table->timestamps();

                $table->unique(['school_id', 'user_id'], 'unique_user_notification_prefs');
                $table->index(['user_id', 'school_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('notifications');
    }
};
