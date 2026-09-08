<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy extends BaseTenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSchoolAdmin();
    }

    public function view(User $user, Invoice $invoice): bool
    {
        if (! $this->isSameSchool($user, $invoice)) {
            return false;
        }

        if ($user->isSchoolAdmin()) {
            return true;
        }

        if ($user->isParent()) {
            return $user->parentProfile && $user->parentProfile->isLinkedTo($invoice->student_id);
        }

        if ($user->isStudent()) {
            return $user->student && (int) $user->student->id === (int) $invoice->student_id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->isSchoolAdmin();
    }

    public function bulkGenerate(User $user): bool
    {
        return $user->isSchoolAdmin();
    }

    public function recordPayment(User $user, Invoice $invoice): bool
    {
        return $this->isSameSchool($user, $invoice) && $user->isSchoolAdmin();
    }

    public function payOnline(User $user, Invoice $invoice): bool
    {
        if (! $this->isSameSchool($user, $invoice)) {
            return false;
        }

        if ($invoice->isPaid()) {
            return false;
        }

        if ($user->isParent()) {
            return $user->parentProfile && $user->parentProfile->isLinkedTo($invoice->student_id);
        }

        return false;
    }
}
