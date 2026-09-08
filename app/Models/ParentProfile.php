<?php

namespace App\Models;

use App\Tenancy\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ParentProfile extends Model
{
    use HasFactory, TenantScoped;

    protected $table = 'parents';

    protected $fillable = [
        'school_id',
        'user_id',
        'occupation',
        'address',
        'phone',
        'emergency_contact',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'parent_student', 'parent_id', 'student_id')
            ->withPivot(['relationship', 'is_primary_contact'])
            ->withTimestamps();
    }

    /**
     * Link a student to this parent.
     */
    public function linkStudent(Student|int $student, string $relationship = 'guardian', bool $isPrimaryContact = false): void
    {
        $studentId = $student instanceof Student ? $student->id : $student;
        $schoolId = $this->school_id;

        $this->students()->syncWithoutDetaching([
            $studentId => [
                'school_id' => $schoolId,
                'relationship' => $relationship,
                'is_primary_contact' => $isPrimaryContact,
            ],
        ]);
    }

    /**
     * Unlink a student from this parent.
     */
    public function unlinkStudent(Student|int $student): void
    {
        $studentId = $student instanceof Student ? $student->id : $student;
        $this->students()->detach($studentId);
    }

    /**
     * Check if parent is linked to a given student.
     */
    public function isLinkedTo(Student|int $student): bool
    {
        $studentId = $student instanceof Student ? $student->id : $student;
        return $this->students()->where('students.id', $studentId)->exists();
    }

    /**
     * Scope search query across occupation, address, or user name/email.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (empty($term)) {
            return $query;
        }

        return $query->where(function ($q) use ($term) {
            $q->where('occupation', 'like', "%{$term}%")
              ->orWhere('phone', 'like', "%{$term}%")
              ->orWhere('address', 'like', "%{$term}%")
              ->orWhereHas('user', function ($uq) use ($term) {
                  $uq->where('name', 'like', "%{$term}%")
                     ->orWhere('email', 'like', "%{$term}%");
              });
        });
    }
}
