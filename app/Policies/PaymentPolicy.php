<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * A payment is visible to both the payer and recipient companies.
     */
    public function view(User $user, Payment $payment): bool
    {
        return $this->isParty($user, $payment);
    }

    public function create(User $user): bool
    {
        return $user->company_id !== null;
    }

    /**
     * Only the payer company can approve or process a payment.
     */
    public function approve(User $user, Payment $payment): bool
    {
        return $user->company_id === $payment->company_id
            && $user->hasPermission('payments.approve');
    }

    public function update(User $user, Payment $payment): bool
    {
        return $this->isParty($user, $payment);
    }

    private function isParty(User $user, Payment $payment): bool
    {
        return $user->company_id === $payment->company_id
            || $user->company_id === $payment->recipient_company_id;
    }
}
