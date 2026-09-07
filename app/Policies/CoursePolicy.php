<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\User;

class CoursePolicy extends BaseTenantPolicy
{
    /**
     * Determine whether the user can view courses.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the specific course.
     */
    public function view(User $user, Course $course): bool
    {
        return $this->isSameSchool($user, $course);
    }

    /**
     * Determine whether the user can create courses.
     */
    public function create(User $user): bool
    {
        return $user->isSchoolAdmin() || $user->isTeacher();
    }

    /**
     * Determine whether the user can update the course.
     */
    public function update(User $user, Course $course): bool
    {
        if (! $this->isSameSchool($user, $course)) {
            return false;
        }

        return $user->isSchoolAdmin() || $user->isTeacher();
    }

    /**
     * Determine whether the user can delete the course.
     */
    public function delete(User $user, Course $course): bool
    {
        if (! $this->isSameSchool($user, $course)) {
            return false;
        }

        // Only school admins can delete courses
        return $user->isSchoolAdmin();
    }
}
