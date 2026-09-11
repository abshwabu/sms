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
        // 1. Add Telegram bot fields to schools table
        Schema::table('schools', function (Blueprint $table) {
            $table->string('telegram_bot_token')->nullable()->after('timezone');
            $table->string('telegram_bot_username')->nullable()->after('telegram_bot_token');
        });

        // 2. Announcements
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('body');
            $table->string('audience_type')->default('all'); // all, grade_level, section, role
            $table->foreignId('grade_level_id')->nullable()->constrained('grade_levels')->nullOnDelete();
            $table->foreignId('section_id')->nullable()->constrained('sections')->nullOnDelete();
            $table->string('target_role')->nullable(); // null (all), parent, student, teacher
            $table->string('priority')->default('normal'); // low, normal, high, urgent
            $table->json('channels')->nullable(); // ["in_app", "email", "telegram"]
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'published_at']);
            $table->index(['school_id', 'audience_type']);
            $table->index('grade_level_id');
            $table->index('section_id');
        });

        // 3. Direct Communication Threads (Teacher - Parent per Student)
        Schema::create('communication_threads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('subject');
            $table->string('status')->default('active'); // active, closed
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'student_id']);
            $table->index(['school_id', 'last_message_at']);
        });

        // 4. Thread Messages
        Schema::create('communication_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('thread_id')->constrained('communication_threads')->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index(['thread_id', 'created_at']);
        });

        // 5. Telegram Accounts Linking
        Schema::create('telegram_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('telegram_chat_id')->nullable();
            $table->string('telegram_username')->nullable();
            $table->string('first_name')->nullable();
            $table->string('link_code')->nullable()->unique();
            $table->timestamp('link_code_expires_at')->nullable();
            $table->boolean('is_linked')->default(false);
            $table->timestamp('linked_at')->nullable();
            $table->boolean('notifications_enabled')->default(true);
            $table->timestamps();

            $table->index(['school_id', 'user_id']);
            $table->index('telegram_chat_id');
        });

        // 6. In-App Notifications
        Schema::create('in_app_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type'); // announcement, message, attendance_alert, grade_published
            $table->string('title');
            $table->text('body');
            $table->json('data')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'user_id', 'read_at']);
            $table->index(['user_id', 'created_at']);
        });

        // 7. Notification Dispatches (Deduplication & Multi-channel Tracking)
        Schema::create('notification_dispatches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('notifiable_type');
            $table->unsignedBigInteger('notifiable_id');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('channel'); // email, telegram, in_app
            $table->string('recipient_address')->nullable();
            $table->string('status')->default('sent'); // queued, sent, failed
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->unique(
                ['school_id', 'notifiable_type', 'notifiable_id', 'user_id', 'channel'],
                'unique_notification_dispatch'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_dispatches');
        Schema::dropIfExists('in_app_notifications');
        Schema::dropIfExists('telegram_accounts');
        Schema::dropIfExists('communication_messages');
        Schema::dropIfExists('communication_threads');
        Schema::dropIfExists('announcements');

        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn(['telegram_bot_token', 'telegram_bot_username']);
        });
    }
};
