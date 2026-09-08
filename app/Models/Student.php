<?php

namespace App\Models;

use App\Tenancy\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends Model
{
    use HasFactory, TenantScoped;

    protected $fillable = [
        'school_id',
        'user_id',
        'admission_number',
        'date_of_birth',
        'gender',
        'address',
        'admission_date',
        'current_section_id',
        'status',
        'medical_notes',
        'photo',
        'guardian_info',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'admission_date' => 'date',
            'guardian_info' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function currentSection(): BelongsTo
    {
        return $this->belongsTo(Section::class, 'current_section_id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class)->orderBy('enrolled_at', 'desc');
    }

    public function parents(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(ParentProfile::class, 'parent_student', 'student_id', 'parent_id')
            ->withPivot(['relationship', 'is_primary_contact'])
            ->withTimestamps();
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class)->orderBy('date', 'desc');
    }

    public function bookLoans(): HasMany
    {
        return $this->hasMany(BookLoan::class)->orderBy('borrowed_at', 'desc');
    }

    public function activeBookLoans(): HasMany
    {
        return $this->hasMany(BookLoan::class)->whereNull('returned_at')->orderBy('due_at', 'asc');
    }

    public function transportAssignment(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(StudentTransport::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class)->orderBy('due_date', 'desc');
    }

    public function payments(): \Illuminate\Database\Eloquent\Relations\HasManyThrough
    {
        return $this->hasManyThrough(Payment::class, Invoice::class);
    }

    /**
     * Scope search across student name, email, and admission number.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (empty($term)) {
            return $query;
        }

        return $query->where(function ($q) use ($term) {
            $q->where('admission_number', 'like', "%{$term}%")
              ->orWhereHas('user', function ($uq) use ($term) {
                  $uq->where('name', 'like', "%{$term}%")
                     ->orWhere('email', 'like', "%{$term}%");
              });
        });
    }

    /**
     * Scope query by current section.
     */
    public function scopeInSection(Builder $query, int|string|null $sectionId): Builder
    {
        if (empty($sectionId)) {
            return $query;
        }

        return $query->where('current_section_id', $sectionId);
    }

    /**
     * Scope query by grade level through current section.
     */
    public function scopeInGradeLevel(Builder $query, int|string|null $gradeLevelId): Builder
    {
        if (empty($gradeLevelId)) {
            return $query;
        }

        return $query->whereHas('currentSection', function ($sq) use ($gradeLevelId) {
            $sq->where('grade_level_id', $gradeLevelId);
        });
    }

    /**
     * Scope query by status.
     */
    public function scopeWithStatus(Builder $query, ?string $status): Builder
    {
        if (empty($status)) {
            return $query;
        }

        return $query->where('status', $status);
    }
}
