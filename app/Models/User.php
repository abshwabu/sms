<?php

namespace App\Models;

use App\Enums\RoleEnum;
use App\Enums\UserStatus;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'name',
        'email',
        'phone',
        'password',
        'role',
        'status',
        'last_login_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
        ];
    }

    /**
     * Get the school that the user belongs to.
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * Get the student profile if the user is a student.
     */
    public function student(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Student::class);
    }

    /**
     * Get the staff profile if the user is staff/teacher.
     */
    public function staff(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Staff::class);
    }

    /**
     * Get the parent profile if the user is a parent.
     */
    public function parentProfile(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(ParentProfile::class);
    }

    /**
     * Invitations sent by this user.
     */
    public function sentInvitations(): HasMany
    {
        return $this->hasMany(Invitation::class, 'invited_by');
    }

    /**
     * Check if the user is a platform-wide Super Admin.
     */
    public function isSuperAdmin(): bool
    {
        return $this->role === RoleEnum::SUPER_ADMIN->value
            || ($this->school_id === null && $this->hasRole(RoleEnum::SUPER_ADMIN->value));
    }

    /**
     * Check if the user is a School Admin.
     */
    public function isSchoolAdmin(): bool
    {
        return $this->role === RoleEnum::SCHOOL_ADMIN->value || $this->hasRole(RoleEnum::SCHOOL_ADMIN->value);
    }

    /**
     * Check if the user is a Teacher.
     */
    public function isTeacher(): bool
    {
        return $this->role === RoleEnum::TEACHER->value || $this->hasRole(RoleEnum::TEACHER->value);
    }

    /**
     * Check if the user is a Student.
     */
    public function isStudent(): bool
    {
        return $this->role === RoleEnum::STUDENT->value || $this->hasRole(RoleEnum::STUDENT->value);
    }

    /**
     * Check if the user is a Parent.
     */
    public function isParent(): bool
    {
        return $this->role === RoleEnum::PARENT->value || $this->hasRole(RoleEnum::PARENT->value);
    }

    /**
     * Check if user account is active.
     */
    public function isActive(): bool
    {
        return $this->status === UserStatus::ACTIVE;
    }

    /**
     * Determine if the user belongs to a specific school tenant.
     */
    public function belongsToSchool(int|string|null $schoolId): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        if ($schoolId === null) {
            return false;
        }

        return (int) $this->school_id === (int) $schoolId;
    }
}
