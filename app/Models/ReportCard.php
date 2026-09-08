<?php

namespace App\Models;

use App\Tenancy\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReportCard extends Model
{
    use HasFactory, TenantScoped;

    protected $table = 'report_cards';

    protected $fillable = [
        'school_id',
        'student_id',
        'section_id',
        'academic_year_id',
        'term_id',
        'grading_scale_id',
        'status',
        'total_marks_obtained',
        'total_max_marks',
        'average_percentage',
        'overall_grade',
        'gpa',
        'rank_in_section',
        'total_students_in_section',
        'homeroom_remarks',
        'principal_remarks',
        'attendance_summary',
        'all_teachers_submitted',
        'published_at',
        'generated_by',
    ];

    protected $casts = [
        'total_marks_obtained' => 'float',
        'total_max_marks' => 'float',
        'average_percentage' => 'float',
        'gpa' => 'float',
        'rank_in_section' => 'integer',
        'total_students_in_section' => 'integer',
        'attendance_summary' => 'array',
        'all_teachers_submitted' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function gradingScale(): BelongsTo
    {
        return $this->belongsTo(GradingScale::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReportCardItem::class);
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function publish(): void
    {
        $this->update([
            'status' => 'published',
            'published_at' => now(),
        ]);
    }
}
