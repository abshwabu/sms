<?php

namespace App\Models;

use App\Tenancy\Exceptions\ClosedAcademicYearException;
use App\Tenancy\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Enrollment extends Model
{
    use HasFactory, TenantScoped;

    protected $fillable = [
        'school_id',
        'student_id',
        'academic_year_id',
        'section_id',
        'enrolled_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'enrolled_at' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function ($enrollment) {
            $year = AcademicYear::withoutGlobalScopes()->find($enrollment->academic_year_id);
            if ($year && $year->isClosed()) {
                throw new ClosedAcademicYearException('Cannot create or update enrollments in a closed academic year.');
            }
        });

        static::deleting(function ($enrollment) {
            $year = AcademicYear::withoutGlobalScopes()->find($enrollment->academic_year_id);
            if ($year && $year->isClosed()) {
                throw new ClosedAcademicYearException('Cannot delete enrollments from a closed academic year.');
            }
        });
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }
}
