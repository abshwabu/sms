<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportCardItem extends Model
{
    use HasFactory;

    protected $table = 'report_card_items';

    protected $fillable = [
        'report_card_id',
        'subject_id',
        'teacher_id',
        'marks_obtained',
        'max_marks',
        'percentage',
        'letter_grade',
        'gpa_point',
        'teacher_remarks',
        'exam_breakdown',
    ];

    protected $casts = [
        'marks_obtained' => 'float',
        'max_marks' => 'float',
        'percentage' => 'float',
        'gpa_point' => 'float',
        'exam_breakdown' => 'array',
    ];

    public function reportCard(): BelongsTo
    {
        return $this->belongsTo(ReportCard::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }
}
