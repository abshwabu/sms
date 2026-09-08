<?php

namespace App\Models;

use App\Tenancy\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory, TenantScoped;

    protected $fillable = [
        'school_id',
        'invoice_id',
        'payment_number',
        'amount',
        'method',
        'paid_at',
        'recorded_by',
        'gateway',
        'gateway_reference',
        'gateway_status',
        'gateway_metadata',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'gateway_metadata' => 'array',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function recordedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function isOnline(): bool
    {
        return $this->method === 'online' || ! empty($this->gateway);
    }

    public function isSuccessful(): bool
    {
        return empty($this->gateway_status) || $this->gateway_status === 'success';
    }
}
