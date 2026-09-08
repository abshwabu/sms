<?php

namespace App\Models;

use App\Tenancy\Exceptions\ClosedAcademicYearException;
use App\Tenancy\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SectionSubjectTeacher extends Model
{
    use HasFactory, TenantScoped;

    protected $table = 'section_subject_teachers';

    protected $fillable = [
        'school_id',
        'section_id',
        'course_id',
        'staff_id',
        'academic_year_id',
    ];

    protected static function booted(): void
    {
        static::saving(function ($assignment) {
            $year = AcademicYear::withoutGlobalScopes()->find($assignment->academic_year_id);
            if ($year && $year->isClosed()) {
                throw new ClosedAcademicYearException('Cannot assign or modify subject teachers in a closed academic year.');
            }
        });

        static::deleting(function ($assignment) {
            $year = AcademicYear::withoutGlobalScopes()->find($assignment->academic_year_id);
            if ($year && $year->isClosed()) {
                throw new ClosedAcademicYearException('Cannot remove subject teachers from a closed academic year.');
            }
        });
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }
}
