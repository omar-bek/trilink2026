<?php

namespace App\Http\Controllers\Web\Concerns;

use App\Models\Contract;

/**
 * Shared contract-resolution and party-authorization for any controller
 * acting on a single contract resource. Extracted so the split sub-
 * controllers (Signing, Amendment, Show, …) can stay self-contained
 * without inheriting from the legacy ContractController god-class.
 *
 * Both methods abort with 404 (not 403) on a non-party access so id
 * enumeration cannot distinguish "doesn't exist" from "exists but you
 * can't see it".
 */
trait AuthorizesContract
{
    protected function findContractOrFail(string $id): Contract
    {
        $query = Contract::query();

        if (str_starts_with($id, 'CTR-') || str_starts_with($id, 'CNT-')) {
            return $query->where('contract_number', $id)->firstOrFail();
        }

        return $query->findOrFail((int) $id);
    }

    protected function authorizeContractParty(Contract $contract): void
    {
        $user = auth()->user();
        if (!$user) {
            abort(404);
        }
        if ($user->isAdmin() || $user->isGovernment()) {
            return;
        }

        $partyCompanyIds = collect($contract->parties ?? [])
            ->pluck('company_id')
            ->push($contract->buyer_company_id)
            ->filter()
            ->unique()
            ->all();

        if (!in_array($user->company_id, $partyCompanyIds, true)) {
            abort(404);
        }
    }
}
