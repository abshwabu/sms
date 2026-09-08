<?php

namespace App\Models;

use App\Tenancy\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LibraryFine extends Model
{
    use HasFactory, TenantScoped;

    protected $fillable = [
        'school_id',
        'book_loan_id',
        'student_id',
        'staff_id',
        'user_id',
        'amount',
        'type',
        'status',
        'paid_at',
        'payment_method',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(BookLoan::class, 'book_loan_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isUnpaid(): bool
    {
        return $this->status === 'unpaid';
    }

    public function isWaived(): bool
    {
        return $this->status === 'waived';
    }

    public function scopeUnpaid(Builder $query): Builder
    {
        return $query->where('status', 'unpaid');
    }

    public function scopePaid(Builder $query): Builder
    {
        return $query->where('status', 'paid');
    }

    public function scopeWaived(Builder $query): Builder
    {
        return $query->where('status', 'waived');
    }
}
