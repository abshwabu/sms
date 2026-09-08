<?php

namespace App\Models;

use App\Tenancy\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use HasFactory, TenantScoped;

    protected $fillable = [
        'school_id',
        'student_id',
        'academic_year_id',
        'term_id',
        'invoice_number',
        'total_amount',
        'paid_amount',
        'due_date',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'due_date' => 'date',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderBy('paid_at', 'desc');
    }

    public function balance(): float
    {
        return (float) max(0, round($this->total_amount - $this->paid_amount, 2));
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isPartiallyPaid(): bool
    {
        return $this->status === 'partial';
    }

    public function isOverdue(): bool
    {
        return ! $this->isPaid() && $this->due_date && $this->due_date->isPast();
    }

    /**
     * Recalculate paid amount and invoice status from completed payments.
     */
    public function recalculateStatus(): void
    {
        $totalPaid = (float) $this->payments()
            ->where(function (Builder $q) {
                $q->whereNull('gateway_status')
                  ->orWhere('gateway_status', 'success');
            })
            ->sum('amount');

        $this->paid_amount = round($totalPaid, 2);

        if ($this->paid_amount >= (float) $this->total_amount) {
            $this->status = 'paid';
        } elseif ($this->paid_amount > 0) {
            $this->status = 'partial';
        } elseif ($this->due_date && $this->due_date->isPast()) {
            $this->status = 'overdue';
        } else {
            $this->status = 'unpaid';
        }

        $this->saveQuietly();
    }

    public function scopeForStudent(Builder $query, int $studentId): Builder
    {
        return $query->where('student_id', $studentId);
    }

    public function scopeForTerm(Builder $query, int $termId): Builder
    {
        return $query->where('term_id', $termId);
    }

    public function scopeWithStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('status', '!=', 'paid')
            ->where('due_date', '<', now()->toDateString());
    }
}
