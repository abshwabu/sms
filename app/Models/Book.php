<?php

namespace App\Models;

use App\Tenancy\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Book extends Model
{
    use HasFactory, TenantScoped;

    protected $fillable = [
        'school_id',
        'title',
        'author',
        'isbn',
        'category',
        'copies_total',
        'copies_available',
        'shelf_location',
        'description',
        'publisher',
        'publication_year',
    ];

    protected function casts(): array
    {
        return [
            'copies_total' => 'integer',
            'copies_available' => 'integer',
            'publication_year' => 'integer',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function loans(): HasMany
    {
        return $this->hasMany(BookLoan::class);
    }

    public function activeLoans(): HasMany
    {
        return $this->hasMany(BookLoan::class)->whereNull('returned_at');
    }

    public function isAvailable(): bool
    {
        return $this->copies_available > 0;
    }

    public function borrowedCopies(): int
    {
        return max(0, $this->copies_total - $this->copies_available);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (empty($term)) {
            return $query;
        }

        return $query->where(function ($q) use ($term) {
            $q->where('title', 'like', "%{$term}%")
              ->orWhere('author', 'like', "%{$term}%")
              ->orWhere('isbn', 'like', "%{$term}%")
              ->orWhere('category', 'like', "%{$term}%")
              ->orWhere('shelf_location', 'like', "%{$term}%");
        });
    }

    public function scopeCategory(Builder $query, ?string $category): Builder
    {
        if (empty($category) || $category === 'all') {
            return $query;
        }

        return $query->where('category', $category);
    }

    public function scopeAvailableOnly(Builder $query): Builder
    {
        return $query->where('copies_available', '>', 0);
    }
}
