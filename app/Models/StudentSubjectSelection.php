<?php

namespace App\Models;

use App\Tenancy\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentSubjectSelection extends Model
{
    use HasFactory, TenantScoped;

    protected $fillable = [
        'school_id',
        'student_id',
        'academic_year_id',
        'subject_id',
        'status',
        'selected_at',
        'selected_by',
    ];

    protected function casts(): array
    {
        return [
            'selected_at' => 'datetime',
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

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function selectedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'selected_by');
    }

    public function scopeEnrolled(Builder $query): Builder
    {
        return $query->where('status', 'enrolled');
    }

    public function scopeDropped(Builder $query): Builder
    {
        return $query->where('status', 'dropped');
    }

    public function scopeForAcademicYear(Builder $query, int $academicYearId): Builder
    {
        return $query->where('academic_year_id', $academicYearId);
    }
}
