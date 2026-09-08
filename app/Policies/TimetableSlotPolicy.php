<?php

namespace App\Policies;

use App\Models\Section;
use App\Models\TimetableSlot;
use App\Models\User;

class TimetableSlotPolicy extends BaseTenantPolicy
{
    /**
     * Determine whether the user can view timetable listings.
     */
    public function viewAny(User $user): bool
    {
        return $user->isSchoolAdmin() || $user->isTeacher() || $user->isStudent() || $user->isParent();
    }

    /**
     * Determine whether the user can view a specific timetable slot.
     */
    public function view(User $user, TimetableSlot $slot): bool
    {
        if (! $this->isSameSchool($user, $slot)) {
            return false;
        }

        if ($user->isSchoolAdmin()) {
            return true;
        }

        if ($user->isTeacher()) {
            return (int) $slot->teacher_id === (int) $user->id 
                || ($slot->section && $slot->section->hasTeacher($user));
        }

        if ($user->isStudent()) {
            return $user->student && (int) $user->student->current_section_id === (int) $slot->section_id;
        }

        if ($user->isParent()) {
            return $user->parentProfile 
                && $user->parentProfile->students()->where('current_section_id', $slot->section_id)->exists();
        }

        return false;
    }

    /**
     * Determine whether the user can create timetable slots for a section.
     */
    public function create(User $user, ?Section $section = null): bool
    {
        if ($user->isSchoolAdmin()) {
            return true;
        }

        if ($user->isTeacher() && $section) {
            return $section->isHomeroomTeacher($user);
        }

        return false;
    }

    /**
     * Determine whether the user can update a timetable slot.
     */
    public function update(User $user, TimetableSlot $slot): bool
    {
        if (! $this->isSameSchool($user, $slot)) {
            return false;
        }

        if ($user->isSchoolAdmin()) {
            return true;
        }

        if ($user->isTeacher() && $slot->section) {
            return $slot->section->isHomeroomTeacher($user);
        }

        return false;
    }

    /**
     * Determine whether the user can delete a timetable slot.
     */
    public function delete(User $user, TimetableSlot $slot): bool
    {
        if (! $this->isSameSchool($user, $slot)) {
            return false;
        }

        if ($user->isSchoolAdmin()) {
            return true;
        }

        if ($user->isTeacher() && $slot->section) {
            return $slot->section->isHomeroomTeacher($user);
        }

        return false;
    }
}
