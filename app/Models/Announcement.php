<?php

namespace App\Models;

use App\Enums\RoleEnum;
use App\Tenancy\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Announcement extends Model
{
    use HasFactory, TenantScoped;

    protected $fillable = [
        'school_id',
        'author_id',
        'title',
        'body',
        'audience_type',
        'grade_level_id',
        'section_id',
        'target_role',
        'priority',
        'channels',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'channels' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function gradeLevel(): BelongsTo
    {
        return $this->belongsTo(GradeLevel::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function dispatches(): HasMany
    {
        return $this->hasMany(NotificationDispatch::class, 'notifiable_id')
            ->where('notifiable_type', self::class);
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null && $this->published_at->isPast();
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    /**
     * Scope announcements visible to a specific user according to audience targeting.
     * Acceptance criterion 1: An announcement targeted at "Grade 3" only appears for Grade 3 parents/students/teachers, not the whole school.
     */
    public function scopeForUser(Builder $query, User $user): Builder
    {
        // Platform or school admins see all announcements for the school
        if ($user->hasAnyRole([RoleEnum::SUPER_ADMIN->value, RoleEnum::SCHOOL_ADMIN->value])) {
            return $query;
        }

        $userRole = $user->role;

        return $query->where(function (Builder $q) use ($user, $userRole) {
            // 1. Audience 'all': targeted to whole school (optionally filtered by role)
            $q->where(function (Builder $allQ) use ($userRole) {
                $allQ->where('audience_type', 'all')
                    ->where(function ($roleQ) use ($userRole) {
                        $roleQ->whereNull('target_role')
                            ->orWhere('target_role', 'all')
                            ->orWhere('target_role', $userRole);
                    });
            });

            // 2. Audience 'role': targeted by user role
            $q->orWhere(function (Builder $roleOnlyQ) use ($userRole) {
                $roleOnlyQ->where('audience_type', 'role')
                    ->where('target_role', $userRole);
            });

            // 3. Audience 'grade_level': targeted to a specific grade level
            $q->orWhere(function (Builder $gradeQ) use ($user, $userRole) {
                $gradeQ->where('audience_type', 'grade_level')
                    ->whereNotNull('grade_level_id')
                    ->where(function ($roleQ) use ($userRole) {
                        $roleQ->whereNull('target_role')
                            ->orWhere('target_role', 'all')
                            ->orWhere('target_role', $userRole);
                    })
                    ->where(function (Builder $targetQ) use ($user) {
                        if ($user->isStudent() && $user->student) {
                            $gradeLevelId = $user->student->currentSection?->grade_level_id;
                            if ($gradeLevelId) {
                                $targetQ->where('grade_level_id', $gradeLevelId);
                            } else {
                                $targetQ->whereRaw('1 = 0');
                            }
                        } elseif ($user->isParent() && $user->parentProfile) {
                            $childSecIds = $user->parentProfile->students()
                                ->whereNotNull('students.current_section_id')
                                ->pluck('students.current_section_id');

                            $childGradeLevelIds = Section::whereIn('id', $childSecIds)
                                ->pluck('grade_level_id')
                                ->unique();

                            if ($childGradeLevelIds->isNotEmpty()) {
                                $targetQ->whereIn('grade_level_id', $childGradeLevelIds);
                            } else {
                                $targetQ->whereRaw('1 = 0');
                            }
                        } elseif ($user->isTeacher()) {
                            $teacherSecIds = Section::where('homeroom_teacher_id', $user->id)
                                ->orWhereHas('subjectTeachers.staff', fn($stQ) => $stQ->where('user_id', $user->id))
                                ->orWhereHas('timetableSlots', fn($ttQ) => $ttQ->where('teacher_id', $user->id))
                                ->pluck('id');

                            $teacherGradeLevelIds = Section::whereIn('id', $teacherSecIds)
                                ->pluck('grade_level_id')
                                ->unique();

                            if ($teacherGradeLevelIds->isNotEmpty()) {
                                $targetQ->whereIn('grade_level_id', $teacherGradeLevelIds);
                            } else {
                                $targetQ->whereRaw('1 = 0');
                            }
                        } else {
                            $targetQ->whereRaw('1 = 0');
                        }
                    });
            });

            // 4. Audience 'section': targeted to a specific section
            $q->orWhere(function (Builder $secQ) use ($user, $userRole) {
                $secQ->where('audience_type', 'section')
                    ->whereNotNull('section_id')
                    ->where(function ($roleQ) use ($userRole) {
                        $roleQ->whereNull('target_role')
                            ->orWhere('target_role', 'all')
                            ->orWhere('target_role', $userRole);
                    })
                    ->where(function (Builder $targetQ) use ($user) {
                        if ($user->isStudent() && $user->student) {
                            $targetQ->where('section_id', $user->student->current_section_id);
                        } elseif ($user->isParent() && $user->parentProfile) {
                            $childSectionIds = $user->parentProfile->students()
                                ->whereNotNull('students.current_section_id')
                                ->pluck('students.current_section_id');

                            if ($childSectionIds->isNotEmpty()) {
                                $targetQ->whereIn('section_id', $childSectionIds);
                            } else {
                                $targetQ->whereRaw('1 = 0');
                            }
                        } elseif ($user->isTeacher()) {
                            $teacherSecIds = Section::where('homeroom_teacher_id', $user->id)
                                ->orWhereHas('subjectTeachers.staff', fn($stQ) => $stQ->where('user_id', $user->id))
                                ->orWhereHas('timetableSlots', fn($ttQ) => $ttQ->where('teacher_id', $user->id))
                                ->pluck('id');

                            if ($teacherSecIds->isNotEmpty()) {
                                $targetQ->whereIn('section_id', $teacherSecIds);
                            } else {
                                $targetQ->whereRaw('1 = 0');
                            }
                        } else {
                            $targetQ->whereRaw('1 = 0');
                        }
                    });
            });
        });
    }
}
