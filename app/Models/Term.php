<?php

namespace App\Models;

use App\Tenancy\Exceptions\ClosedAcademicYearException;
use App\Tenancy\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Term extends Model
{
    use HasFactory, TenantScoped;

    protected $fillable = [
        'school_id',
        'academic_year_id',
        'name',
        'start_date',
        'end_date',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function ($term) {
            if ($term->academicYear && $term->academicYear->isClosed()) {
                throw new ClosedAcademicYearException('Cannot add or update terms in a closed academic year.');
            }
        });

        static::deleting(function ($term) {
            if ($term->academicYear && $term->academicYear->isClosed()) {
                throw new ClosedAcademicYearException('Cannot delete terms in a closed academic year.');
            }
        });
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }
}
