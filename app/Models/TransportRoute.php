<?php

namespace App\Models;

use App\Tenancy\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TransportRoute extends Model
{
    use HasFactory, TenantScoped;

    protected $fillable = [
        'school_id',
        'name',
        'vehicle_info',
        'driver_name',
        'driver_contact',
        'capacity',
        'status',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function stops(): HasMany
    {
        return $this->hasMany(TransportStop::class)->orderBy('sequence', 'asc');
    }

    public function studentAssignments(): HasMany
    {
        return $this->hasMany(StudentTransport::class);
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'student_transport')
            ->withPivot(['transport_stop_id', 'status', 'notes'])
            ->withTimestamps();
    }

    public function getAssignedCountAttribute(): int
    {
        return $this->studentAssignments()->where('status', 'active')->count();
    }

    public function getAvailableCapacityAttribute(): int
    {
        return max(0, $this->capacity - $this->assigned_count);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (empty($term)) {
            return $query;
        }

        return $query->where(function ($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
              ->orWhere('vehicle_info', 'like', "%{$term}%")
              ->orWhere('driver_name', 'like', "%{$term}%")
              ->orWhere('driver_contact', 'like', "%{$term}%");
        });
    }
}
