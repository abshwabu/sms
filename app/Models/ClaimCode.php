<?php

namespace App\Models;

use App\Tenancy\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ClaimCode extends Model
{
    use HasFactory, TenantScoped;

    protected $fillable = [
        'school_id',
        'code',
        'role',
        'student_id',
        'claimed_by',
        'expires_at',
        'claimed_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'claimed_at' => 'datetime',
        ];
    }

    /**
     * Associated student user if applicable (e.g. for parent claim flow).
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /**
     * User who claimed this code.
     */
    public function claimer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'claimed_by');
    }

    /**
     * Check if the claim code is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Check if the claim code has already been claimed.
     */
    public function isClaimed(): bool
    {
        return $this->claimed_at !== null;
    }

    /**
     * Mark claim code as claimed by user.
     */
    public function markAsClaimed(User $user): void
    {
        $this->update([
            'claimed_by' => $user->id,
            'claimed_at' => now(),
        ]);
    }

    /**
     * Generate a human-readable claim code (e.g. BINA-7K9P-X2).
     */
    public static function generateCode(): string
    {
        return 'BINA-' . strtoupper(Str::random(4)) . '-' . strtoupper(Str::random(4));
    }
}
