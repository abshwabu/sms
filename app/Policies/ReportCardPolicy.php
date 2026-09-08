<?php

namespace App\Policies;

use App\Models\ReportCard;
use App\Models\User;

class ReportCardPolicy extends BaseTenantPolicy
{
    /**
     * Determine whether the user can view any report cards.
     */
    public function viewAny(User $user): bool
    {
        return $user->isSchoolAdmin() || $user->isTeacher() || $user->isStudent() || $user->isParent();
    }

    /**
     * Determine whether the user can view a specific report card.
     */
    public function view(User $user, ReportCard $reportCard): bool
    {
        if (! $this->isSameSchool($user, $reportCard)) {
            return false;
        }

        // Admins and teachers can view both draft and published report cards
        if ($user->isSchoolAdmin() || $user->isTeacher()) {
            return true;
        }

        // Students can only view their own report cards once published
        if ($user->isStudent()) {
            return $reportCard->isPublished() 
                && $user->student 
                && (int) $user->student->id === (int) $reportCard->student_id;
        }

        // Parents can only view their linked children's report cards once published
        if ($user->isParent()) {
            return $reportCard->isPublished() 
                && $user->parentProfile 
                && $user->parentProfile->isLinkedTo($reportCard->student_id);
        }

        return false;
    }

    /**
     * Determine whether the user can create report cards.
     */
    public function create(User $user): bool
    {
        return $user->isSchoolAdmin() || $user->isTeacher();
    }

    /**
     * Determine whether the user can update a report card.
     */
    public function update(User $user, ReportCard $reportCard): bool
    {
        if (! $this->isSameSchool($user, $reportCard)) {
            return false;
        }

        if ($user->isSchoolAdmin()) {
            return true;
        }

        if ($user->isTeacher()) {
            return $reportCard->section && $reportCard->section->isHomeroomTeacher($user);
        }

        return false;
    }

    /**
     * Determine whether the user can publish report cards.
     */
    public function publish(User $user, ReportCard $reportCard): bool
    {
        if (! $this->isSameSchool($user, $reportCard)) {
            return false;
        }

        if ($user->isSchoolAdmin()) {
            return true;
        }

        if ($user->isTeacher()) {
            return $reportCard->section && $reportCard->section->isHomeroomTeacher($user);
        }

        return false;
    }

    /**
     * Determine whether the user can download a report card as PDF.
     */
    public function downloadPdf(User $user, ReportCard $reportCard): bool
    {
        return $this->view($user, $reportCard);
    }
}
