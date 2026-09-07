<?php

namespace App\Models;

use App\Tenancy\Exceptions\ClosedAcademicYearException;
use App\Tenancy\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentSectionAssignment extends Model
{
    use HasFactory, TenantScoped;

    protected $fillable = [
        'school_id',
        'academic_year_id',
        'section_id',
        'student_id',
        'roll_number',
        'status',
        'enrolled_at',
    ];

    protected function casts(): array
    {
        return [
            'enrolled_at' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function ($assignment) {
            if ($assignment->academicYear && $assignment->academicYear->isClosed()) {
                throw new ClosedAcademicYearException('Cannot enroll or modify student assignments in a closed academic year.');
            }
        });

        static::deleting(function ($assignment) {
            if ($assignment->academicYear && $assignment->academicYear->isClosed()) {
                throw new ClosedAcademicYearException('Cannot remove student assignments in a closed academic year.');
            }
        });
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}
