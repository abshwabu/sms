<?php

namespace App\Models;

use App\Tenancy\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subject extends Model
{
    use HasFactory, TenantScoped;

    protected $table = 'subjects';

    protected $fillable = [
        'school_id',
        'grade_level_id',
        'course_id',
        'name',
        'code',
        'credit_hours',
        'description',
        'is_elective',
    ];

    protected $casts = [
        'credit_hours' => 'float',
        'is_elective' => 'boolean',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function gradeLevel(): BelongsTo
    {
        return $this->belongsTo(GradeLevel::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class);
    }

    public function reportCardItems(): HasMany
    {
        return $this->hasMany(ReportCardItem::class);
    }

    public function timetableSlots(): HasMany
    {
        return $this->hasMany(TimetableSlot::class);
    }

    public function offerings(): HasMany
    {
        return $this->hasMany(SubjectOffering::class);
    }

    public function studentSelections(): HasMany
    {
        return $this->hasMany(StudentSubjectSelection::class);
    }
}
