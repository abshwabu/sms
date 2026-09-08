<?php

namespace App\Models;

use App\Tenancy\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TransportStop extends Model
{
    use HasFactory, TenantScoped;

    protected $fillable = [
        'school_id',
        'transport_route_id',
        'stop_name',
        'pickup_time',
        'dropoff_time',
        'sequence',
        'landmark',
    ];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(TransportRoute::class, 'transport_route_id');
    }

    public function studentAssignments(): HasMany
    {
        return $this->hasMany(StudentTransport::class, 'transport_stop_id');
    }

    public function getAssignedStudentsCountAttribute(): int
    {
        return $this->studentAssignments()->where('status', 'active')->count();
    }
}
