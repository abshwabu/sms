<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

class PaymentPolicy extends BaseTenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSchoolAdmin();
    }

    public function view(User $user, Payment $payment): bool
    {
        if (! $this->isSameSchool($user, $payment)) {
            return false;
        }

        if ($user->isSchoolAdmin()) {
            return true;
        }

        $invoice = $payment->invoice;
        if (! $invoice) {
            return false;
        }

        if ($user->isParent()) {
            return $user->parentProfile && $user->parentProfile->isLinkedTo($invoice->student_id);
        }

        if ($user->isStudent()) {
            return $user->student && (int) $user->student->id === (int) $invoice->student_id;
        }

        return false;
    }

    public function viewReceipt(User $user, Payment $payment): bool
    {
        return $this->view($user, $payment);
    }
}
