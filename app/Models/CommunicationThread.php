<?php

namespace App\Models;

use App\Enums\RoleEnum;
use App\Tenancy\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommunicationThread extends Model
{
    use HasFactory, TenantScoped;

    protected $fillable = [
        'school_id',
        'student_id',
        'created_by',
        'subject',
        'status',
        'last_message_at',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(CommunicationMessage::class, 'thread_id')->oldest();
    }

    public function latestMessage()
    {
        return $this->hasOne(CommunicationMessage::class, 'thread_id')->latestOfMany();
    }

    /**
     * Check if a given user is allowed to view and participate in this student thread.
     * Acceptance criterion 2: Teacher-parent message thread is scoped to the specific student
     * and visible to both linked parents (if two) plus the relevant teacher(s).
     */
    public function isParticipant(User $user): bool
    {
        if ($user->hasAnyRole([RoleEnum::SUPER_ADMIN->value, RoleEnum::SCHOOL_ADMIN->value])) {
            return true;
        }

        // Student's linked parents (both parents if two exist)
        if ($user->isParent()) {
            return $this->student->parents()
                ->where('parents.user_id', $user->id)
                ->exists();
        }

        // Relevant teachers for this student
        if ($user->isTeacher()) {
            $student = $this->student;
            $section = $student->currentSection;

            if (! $section) {
                return false;
            }

            // Homeroom teacher of student's current section
            if ($section->homeroom_teacher_id === $user->id) {
                return true;
            }

            // Subject teacher assigned to student's section
            if ($section->subjectTeachers()->whereHas('staff', fn($q) => $q->where('user_id', $user->id))->exists()) {
                return true;
            }

            // Also check timetable slot teachers for this section
            if ($section->timetableSlots()->where('teacher_id', $user->id)->exists()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Scope query to only threads visible to the given user.
     */
    public function scopeForUser(Builder $query, User $user): Builder
    {
        if ($user->hasAnyRole([RoleEnum::SUPER_ADMIN->value, RoleEnum::SCHOOL_ADMIN->value])) {
            return $query;
        }

        if ($user->isParent() && $user->parentProfile) {
            $linkedStudentIds = $user->parentProfile->students()->pluck('students.id');
            return $query->whereIn('student_id', $linkedStudentIds);
        }

        if ($user->isTeacher()) {
            return $query->whereHas('student.currentSection', function ($secQ) use ($user) {
                $secQ->where('homeroom_teacher_id', $user->id)
                    ->orWhereHas('subjectTeachers.staff', function ($stQ) use ($user) {
                        $stQ->where('user_id', $user->id);
                    })
                    ->orWhereHas('timetableSlots', function ($ttQ) use ($user) {
                        $ttQ->where('teacher_id', $user->id);
                    });
            });
        }

        // Disallow students or other unauthorized roles
        return $query->whereRaw('1 = 0');
    }
}
