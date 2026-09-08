<?php

namespace App\Models;

use App\Tenancy\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPreference extends Model
{
    use HasFactory, TenantScoped;

    protected $table = 'notification_preferences';

    protected $fillable = [
        'school_id',
        'user_id',
        'email_enabled',
        'telegram_enabled',
        'sms_enabled',
        'push_enabled',
        'attendance_alerts',
        'grade_alerts',
        'announcement_alerts',
        'library_alerts',
        'message_alerts',
    ];

    protected $casts = [
        'email_enabled' => 'boolean',
        'telegram_enabled' => 'boolean',
        'sms_enabled' => 'boolean',
        'push_enabled' => 'boolean',
        'attendance_alerts' => 'boolean',
        'grade_alerts' => 'boolean',
        'announcement_alerts' => 'boolean',
        'library_alerts' => 'boolean',
        'message_alerts' => 'boolean',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get or create default preferences for a user.
     */
    public static function forUser(User $user, ?int $schoolId = null): self
    {
        $resolvedSchoolId = $schoolId ?: $user->school_id ?: app(\App\Tenancy\TenantManager::class)->getTenantId();

        return static::withoutGlobalScopes()->firstOrCreate(
            [
                'school_id' => $resolvedSchoolId,
                'user_id' => $user->id,
            ],
            [
                'email_enabled' => true,
                'telegram_enabled' => true,
                'sms_enabled' => false,
                'push_enabled' => false,
                'attendance_alerts' => true,
                'grade_alerts' => true,
                'announcement_alerts' => true,
                'library_alerts' => true,
                'message_alerts' => true,
            ]
        );
    }

    /**
     * Check if a channel delivery is permitted for a given category.
     */
    public function shouldSend(string $channel, ?string $category = null): bool
    {
        // 1. Channel level check
        $channelAllowed = match (strtolower($channel)) {
            'in_app' => true, // In-app is always delivered
            'email' => (bool) $this->email_enabled,
            'telegram' => (bool) $this->telegram_enabled,
            'sms' => (bool) $this->sms_enabled,
            'push' => (bool) $this->push_enabled,
            default => true,
        };

        if (! $channelAllowed) {
            return false;
        }

        // 2. Category level check
        if ($category) {
            $categoryAllowed = match (strtolower($category)) {
                'attendance', 'attendance_absence', 'absence' => (bool) $this->attendance_alerts,
                'grade', 'grade_published', 'report_card', 'report_card_published' => (bool) $this->grade_alerts,
                'announcement', 'announcements' => (bool) $this->announcement_alerts,
                'library', 'library_due', 'overdue_book' => (bool) $this->library_alerts,
                'message', 'communication', 'message_received' => (bool) $this->message_alerts,
                default => true,
            };

            if (! $categoryAllowed) {
                return false;
            }
        }

        return true;
    }
}
