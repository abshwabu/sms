<?php

namespace App\Models;

use App\Tenancy\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeeStructure extends Model
{
    use HasFactory, TenantScoped;

    protected $fillable = [
        'school_id',
        'academic_year_id',
        'term_id',
        'grade_level_id',
        'name',
        'category',
        'amount',
        'is_mandatory',
        'condition_type',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'is_mandatory' => 'boolean',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function gradeLevel(): BelongsTo
    {
        return $this->belongsTo(GradeLevel::class);
    }

    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function scopeForTerm(Builder $query, int $termId): Builder
    {
        return $query->where('term_id', $termId);
    }

    public function scopeForGrade(Builder $query, ?int $gradeLevelId): Builder
    {
        if ($gradeLevelId === null) {
            return $query->whereNull('grade_level_id');
        }

        return $query->where(function (Builder $q) use ($gradeLevelId) {
            $q->where('grade_level_id', $gradeLevelId)
              ->orWhereNull('grade_level_id');
        });
    }

    /**
     * Determine if this fee structure requires conditional qualification.
     */
    public function isConditional(): bool
    {
        return $this->condition_type !== 'none' || $this->category === 'transport';
    }

    /**
     * Check if this fee item applies to a given student.
     */
    public function appliesToStudent(Student $student, ?AcademicYear $academicYear = null): bool
    {
        // 1. Grade check (if specified)
        if ($this->grade_level_id !== null) {
            $studentGradeId = $student->currentSection?->grade_level_id;
            if ($studentGradeId && (int) $studentGradeId !== (int) $this->grade_level_id) {
                return false;
            }
        }

        // 2. Condition check
        if ($this->condition_type === 'transport_enrollment' || $this->category === 'transport') {
            return StudentTransport::withoutGlobalScopes()
                ->where('student_id', $student->id)
                ->where('status', 'active')
                ->exists();
        }

        return true;
    }
}
