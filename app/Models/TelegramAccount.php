<?php

namespace App\Models;

use App\Tenancy\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelegramAccount extends Model
{
    use HasFactory, TenantScoped;

    protected $fillable = [
        'school_id',
        'user_id',
        'telegram_chat_id',
        'telegram_username',
        'first_name',
        'link_code',
        'link_code_expires_at',
        'is_linked',
        'linked_at',
        'notifications_enabled',
    ];

    protected function casts(): array
    {
        return [
            'is_linked' => 'boolean',
            'notifications_enabled' => 'boolean',
            'link_code_expires_at' => 'datetime',
            'linked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function isCodeValid(): bool
    {
        return $this->link_code_expires_at ? $this->link_code_expires_at->isFuture() : false;
    }
}
