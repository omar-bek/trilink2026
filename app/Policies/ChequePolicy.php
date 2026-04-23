<?php

namespace App\Policies;

use App\Models\PostdatedCheque;
use App\Models\User;

/**
 * Post-dated cheque authorisation. The issuer and beneficiary companies
 * can both see the cheque, but only the holder side (beneficiary) can
 * deposit / clear / return / stop it. The issuer can only stop it or
 * replace it.
 */
class ChequePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return null;
    }

    public function view(User $user, PostdatedCheque $cheque): bool
    {
        return $this->isParty($user, $cheque);
    }

    public function register(User $user): bool
    {
        return $user->hasPermissionTo('payment.approve');
    }

    public function deposit(User $user, PostdatedCheque $cheque): bool
    {
        return $this->isBeneficiary($user, $cheque)
            && $user->hasPermissionTo('payment.approve');
    }

    public function clear(User $user, PostdatedCheque $cheque): bool
    {
        return $this->isBeneficiary($user, $cheque)
            && $user->hasPermissionTo('payment.approve');
    }

    public function returnCheque(User $user, PostdatedCheque $cheque): bool
    {
        return $this->isBeneficiary($user, $cheque)
            && $user->hasPermissionTo('payment.approve');
    }

    public function stop(User $user, PostdatedCheque $cheque): bool
    {
        return $this->isIssuer($user, $cheque)
            && $user->hasPermissionTo('payment.approve');
    }

    public function replace(User $user, PostdatedCheque $cheque): bool
    {
        return $this->isIssuer($user, $cheque)
            && $user->hasPermissionTo('payment.approve');
    }

    private function isParty(User $user, PostdatedCheque $cheque): bool
    {
        return $this->isIssuer($user, $cheque) || $this->isBeneficiary($user, $cheque);
    }

    private function isIssuer(User $user, PostdatedCheque $cheque): bool
    {
        return $user->company_id !== null
            && $user->company_id === (int) $cheque->issuer_company_id;
    }

    private function isBeneficiary(User $user, PostdatedCheque $cheque): bool
    {
        return $user->company_id !== null
            && $user->company_id === (int) $cheque->beneficiary_company_id;
    }
}
