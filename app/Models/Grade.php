<?php

namespace App\Models;

use App\Tenancy\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Grade extends Model
{
    use HasFactory, TenantScoped;

    protected $table = 'grades';

    protected $fillable = [
        'school_id',
        'student_id',
        'subject_id',
        'exam_id',
        'section_id',
        'marks_obtained',
        'max_marks',
        'entered_by',
        'remarks',
    ];

    protected $casts = [
        'marks_obtained' => 'float',
        'max_marks' => 'float',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by');
    }

    public function getPercentageAttribute(): float
    {
        $max = max(1.0, (float) $this->max_marks);
        return round(((float) $this->marks_obtained / $max) * 100, 2);
    }
}
