<?php

namespace App\Models;

use App\Tenancy\Traits\TenantScoped;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BookLoan extends Model
{
    use HasFactory, TenantScoped;

    protected $fillable = [
        'school_id',
        'book_id',
        'student_id',
        'staff_id',
        'user_id',
        'borrowed_at',
        'due_at',
        'returned_at',
        'status',
        'fine_amount',
        'fine_paid',
        'fine_paid_at',
        'checked_out_by',
        'checked_in_by',
        'notes',
    ];

    protected $appends = [
        'is_overdue',
        'days_overdue',
        'borrower_name',
        'borrower_type',
    ];

    protected function casts(): array
    {
        return [
            'borrowed_at' => 'datetime',
            'due_at' => 'datetime',
            'returned_at' => 'datetime',
            'fine_paid_at' => 'datetime',
            'fine_amount' => 'decimal:2',
            'fine_paid' => 'boolean',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
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

    public function checkedOutBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_out_by');
    }

    public function checkedInBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_in_by');
    }

    public function fines(): HasMany
    {
        return $this->hasMany(LibraryFine::class, 'book_loan_id');
    }

    public function getIsOverdueAttribute(): bool
    {
        if ($this->returned_at !== null) {
            return $this->returned_at->startOfDay()->gt($this->due_at->startOfDay());
        }

        return now()->startOfDay()->gt($this->due_at->startOfDay());
    }

    public function getDaysOverdueAttribute(): int
    {
        $comparisonDate = $this->returned_at ?? now();

        if ($comparisonDate->startOfDay()->gt($this->due_at->startOfDay())) {
            return (int) $this->due_at->startOfDay()->diffInDays($comparisonDate->startOfDay());
        }

        return 0;
    }

    public function getBorrowerNameAttribute(): string
    {
        if ($this->student && $this->student->user) {
            return $this->student->user->name;
        }

        if ($this->staff && $this->staff->user) {
            return $this->staff->user->name;
        }

        if ($this->user) {
            return $this->user->name;
        }

        return 'Unknown Borrower';
    }

    public function getBorrowerTypeAttribute(): string
    {
        if ($this->student_id) {
            return 'student';
        }

        if ($this->staff_id) {
            return 'staff';
        }

        return 'user';
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('returned_at');
    }

    public function scopeReturned(Builder $query): Builder
    {
        return $query->whereNotNull('returned_at');
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->whereNull('returned_at')->where('due_at', '<', now());
    }
}
