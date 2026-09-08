<?php

namespace App\Models;

use App\Tenancy\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Exam extends Model
{
    use HasFactory, TenantScoped;

    protected $table = 'exams';

    protected $fillable = [
        'school_id',
        'academic_year_id',
        'term_id',
        'grade_level_id',
        'name',
        'type',
        'weight',
        'max_marks',
        'date',
        'status',
    ];

    protected $casts = [
        'weight' => 'float',
        'max_marks' => 'float',
        'date' => 'date:Y-m-d',
    ];

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

    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class);
    }
}
