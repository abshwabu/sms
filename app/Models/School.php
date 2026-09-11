<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class School extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'subdomain',
        'logo',
        'address',
        'contact_info',
        'subscription_status',
        'timezone',
        'telegram_bot_token',
        'telegram_bot_username',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'telegram_bot_token',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'has_telegram_bot',
        'telegram_webhook_url',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'contact_info' => 'array',
        ];
    }

    /**
     * Courses belonging to this school.
     */
    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }

    /**
     * Users belonging to this school.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Determine if the school has a Telegram bot token configured.
     */
    public function hasTelegramBot(): bool
    {
        return !empty($this->telegram_bot_token);
    }

    /**
     * Accessor for whether the school has a configured Telegram bot.
     */
    public function getHasTelegramBotAttribute(): bool
    {
        return $this->hasTelegramBot();
    }

    /**
     * Accessor for the school's unique Telegram webhook endpoint URL.
     */
    public function getTelegramWebhookUrlAttribute(): string
    {
        return url("/api/telegram/webhook/{$this->id}");
    }

    /**
     * Get a safely masked preview of the Telegram bot token.
     */
    public function getMaskedTelegramBotTokenAttribute(): ?string
    {
        if (empty($this->telegram_bot_token)) {
            return null;
        }

        $token = (string) $this->telegram_bot_token;
        $parts = explode(':', $token, 2);
        if (count($parts) === 2) {
            $prefix = $parts[0] . ':';
            $secret = $parts[1];
            $visibleEnd = strlen($secret) > 4 ? substr($secret, -4) : '';
            return $prefix . '••••••••••••••••••••••••' . $visibleEnd;
        }

        return substr($token, 0, min(6, strlen($token))) . '••••••••••••••••';
    }
}
