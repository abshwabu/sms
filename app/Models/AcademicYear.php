<?php

namespace App\Models;

use App\Tenancy\Exceptions\ClosedAcademicYearException;
use App\Tenancy\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademicYear extends Model
{
    use HasFactory, TenantScoped;

    protected $fillable = [
        'school_id',
        'name',
        'start_date',
        'end_date',
        'is_active',
        'is_closed',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_active' => 'boolean',
            'is_closed' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function ($year) {
            // If the year is already closed and we are modifying academic details (not just re-opening)
            if ($year->getOriginal('is_closed') && $year->is_closed) {
                throw new ClosedAcademicYearException('Cannot modify records in a closed academic year. Historical academic years are read-only.');
            }
        });

        static::deleting(function ($year) {
            if ($year->is_closed) {
                throw new ClosedAcademicYearException('Cannot delete a closed historical academic year.');
            }
        });
    }

    public function terms(): HasMany
    {
        return $this->hasMany(Term::class)->orderBy('start_date');
    }

    public function sections(): HasMany
    {
        return $this->hasMany(Section::class);
    }

    public function studentAssignments(): HasMany
    {
        return $this->hasMany(StudentSectionAssignment::class);
    }

    public function isClosed(): bool
    {
        return (bool) $this->is_closed;
    }

    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }

    public function close(): void
    {
        $this->update([
            'is_closed' => true,
            'is_active' => false,
        ]);
    }

    public function activate(): void
    {
        // Deactivate other academic years in this school
        static::where('school_id', $this->school_id)
            ->where('id', '!=', $this->id)
            ->update(['is_active' => false]);

        $this->update([
            'is_active' => true,
            'is_closed' => false,
        ]);
    }
}
