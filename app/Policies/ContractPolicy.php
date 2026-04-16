<?php

namespace App\Policies;

use App\Models\Contract;
use App\Models\User;

class ContractPolicy
{
    /**
     * Admins and government users can see everything.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isAdmin() || $user->isGovernment()) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Contract $contract): bool
    {
        return $this->isParty($user, $contract);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('contracts.create');
    }

    public function update(User $user, Contract $contract): bool
    {
        return $this->isParty($user, $contract);
    }

    public function delete(User $user, Contract $contract): bool
    {
        return $this->isParty($user, $contract);
    }

    public function sign(User $user, Contract $contract): bool
    {
        return $this->isParty($user, $contract);
    }

    /**
     * A user is a contract party if their company is either the buyer
     * or listed in the parties JSON array.
     */
    private function isParty(User $user, Contract $contract): bool
    {
        $partyCompanyIds = collect($contract->parties ?? [])
            ->pluck('company_id')
            ->push($contract->buyer_company_id)
            ->filter()
            ->unique()
            ->all();

        return in_array($user->company_id, $partyCompanyIds, false);
    }
}
