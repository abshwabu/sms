<?php

namespace App\Policies;

use App\Models\CommunicationThread;
use App\Models\User;

class CommunicationThreadPolicy extends BaseTenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin()
            || $user->isSchoolAdmin()
            || $user->isTeacher()
            || $user->isParent()
            || $user->isStudent();
    }

    public function view(User $user, CommunicationThread $thread): bool
    {
        return $thread->isParticipant($user);
    }

    public function reply(User $user, CommunicationThread $thread): bool
    {
        return $thread->isParticipant($user);
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin()
            || $user->isSchoolAdmin()
            || $user->isTeacher()
            || $user->isParent();
    }
}
