<?php

namespace App\Models;

use App\Tenancy\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GradingScale extends Model
{
    use HasFactory, TenantScoped;

    protected $table = 'grading_scales';

    protected $fillable = [
        'school_id',
        'name',
        'scale_type',
        'is_default',
        'rules',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'rules' => 'array',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * Determine letter grade, GPA point, and qualitative description for a given score percentage.
     */
    public function calculateGrade(float $percentage): array
    {
        $rules = $this->rules ?? [];

        // Sort descending by min_score
        usort($rules, fn ($a, $b) => ($b['min_score'] ?? 0) <=> ($a['min_score'] ?? 0));

        foreach ($rules as $bracket) {
            $min = (float) ($bracket['min_score'] ?? 0);
            $max = (float) ($bracket['max_score'] ?? 100);

            if ($percentage >= $min && $percentage <= $max) {
                return [
                    'grade' => (string) ($bracket['grade'] ?? 'Pass'),
                    'gpa_point' => (float) ($bracket['gpa_point'] ?? 0.0),
                    'description' => (string) ($bracket['description'] ?? ''),
                ];
            }
        }

        // Fallback lowest
        $lastBracket = end($rules);
        if ($lastBracket) {
            return [
                'grade' => (string) ($lastBracket['grade'] ?? 'F'),
                'gpa_point' => (float) ($lastBracket['gpa_point'] ?? 0.0),
                'description' => (string) ($lastBracket['description'] ?? 'Fail'),
            ];
        }

        return [
            'grade' => $percentage >= 50 ? 'Pass' : 'Fail',
            'gpa_point' => $percentage >= 50 ? 2.0 : 0.0,
            'description' => $percentage >= 50 ? 'Satisfactory' : 'Unsatisfactory',
        ];
    }

    /**
     * Get or create a default standard grading scale for a school.
     */
    public static function getDefaultScale(int $schoolId): self
    {
        $scale = static::withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->where('is_default', true)
            ->first();

        if ($scale) {
            return $scale;
        }

        // Default standard letter scale
        return static::withoutGlobalScopes()->create([
            'school_id' => $schoolId,
            'name' => 'Standard Letter (A–F)',
            'scale_type' => 'letter',
            'is_default' => true,
            'rules' => [
                ['min_score' => 90, 'max_score' => 100, 'grade' => 'A', 'gpa_point' => 4.0, 'description' => 'Excellent'],
                ['min_score' => 80, 'max_score' => 89.99, 'grade' => 'B', 'gpa_point' => 3.0, 'description' => 'Good'],
                ['min_score' => 70, 'max_score' => 79.99, 'grade' => 'C', 'gpa_point' => 2.0, 'description' => 'Satisfactory'],
                ['min_score' => 60, 'max_score' => 69.99, 'grade' => 'D', 'gpa_point' => 1.0, 'description' => 'Pass'],
                ['min_score' => 0, 'max_score' => 59.99, 'grade' => 'F', 'gpa_point' => 0.0, 'description' => 'Fail'],
            ],
        ]);
    }
}
