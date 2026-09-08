<?php

namespace App\Policies;

use App\Models\Announcement;
use App\Models\User;

class AnnouncementPolicy extends BaseTenantPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Announcement $announcement): bool
    {
        if ($user->isSuperAdmin() || $user->isSchoolAdmin()) {
            return $this->isSameSchool($user, $announcement) || $user->isSuperAdmin();
        }

        if (! $this->isSameSchool($user, $announcement)) {
            return false;
        }

        // Must be published or author
        if (! $announcement->isPublished() && (int) $announcement->author_id !== (int) $user->id) {
            return false;
        }

        // Verify audience targeting
        return Announcement::forUser($user)->where('id', $announcement->id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin()
            || $user->isSchoolAdmin()
            || $user->isTeacher();
    }

    public function update(User $user, Announcement $announcement): bool
    {
        if ($user->isSuperAdmin() || $user->isSchoolAdmin()) {
            return $this->isSameSchool($user, $announcement) || $user->isSuperAdmin();
        }

        return (int) $announcement->author_id === (int) $user->id;
    }

    public function delete(User $user, Announcement $announcement): bool
    {
        if ($user->isSuperAdmin() || $user->isSchoolAdmin()) {
            return $this->isSameSchool($user, $announcement) || $user->isSuperAdmin();
        }

        return (int) $announcement->author_id === (int) $user->id;
    }
}
