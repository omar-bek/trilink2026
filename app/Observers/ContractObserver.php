<?php

namespace App\Observers;

use App\Jobs\SendContractNotificationsJob;
use App\Models\Contract;
use App\Models\ContractParty;
use App\Notifications\ContractCreatedNotification;
use App\Notifications\ContractSignatureRequestedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Single source of truth for "a contract was just created — tell
 * everyone." Previously this dispatch lived inside
 * {@see \App\Services\ContractService::createFromBid()} only, which
 * meant the buy-now flow and the cart-checkout flow created contracts
 * silently with no email + no in-app notification. The observer
 * guarantees every contract creation path goes through the same
 * fan-out regardless of which entry point built it.
 *
 * Delivery is dispatched to {@see SendContractNotificationsJob} on
 * the `notifications` queue so the request that created the contract
 * returns immediately, and the per-company recipient_roles filter
 * is applied at the worker so a 50-employee company doesn't get 50
 * emails per event.
 *
 * Sprint Hardening — also responsible for syncing the
 * `contract_parties` denormalized junction table from the canonical
 * `parties` JSON column on every create/update. The junction table
 * is the indexed read path; the JSON column remains the writable
 * canonical store.
 */
class ContractObserver
{
    public function created(Contract $contract): void
    {
        $this->syncJunction($contract);
        $this->fanOutCreatedNotification($contract);
        $this->fanOutSignatureRequestedNotification($contract);
    }

    public function updated(Contract $contract): void
    {
        // Re-sync only when the parties JSON or the buyer_company_id
        // actually changed — every other update (status flip, payment
        // schedule edit, etc.) leaves the party set untouched and
        // we don't need to thrash the junction.
        if ($contract->wasChanged('parties') || $contract->wasChanged('buyer_company_id')) {
            $this->syncJunction($contract);
        }
    }

    public function deleted(Contract $contract): void
    {
        // Belt and braces — the migration declares cascadeOnDelete on
        // the FK, but SQLite foreign keys are not enforced in some
        // test environments and a stray junction row would silently
        // pollute every supplier's contract list. Wiping explicitly
        // here makes the cleanup observable AND testable.
        if (Schema::hasTable('contract_parties')) {
            try {
                ContractParty::where('contract_id', $contract->id)->delete();
            } catch (\Throwable $e) {
                \Log::warning('ContractObserver::deleted junction cleanup failed', [
                    'contract_id' => $contract->id,
                    'error'       => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Rebuild the junction rows for one contract from the canonical
     * `parties` JSON + the `buyer_company_id` column. Idempotent —
     * safe to call multiple times for the same contract. Each call
     * computes the desired set, deletes nothing-extra, and upserts
     * the rest. We delete-then-insert inside a transaction so a
     * race between two concurrent observers can't leave the index
     * with an outdated row.
     */
    private function syncJunction(Contract $contract): void
    {
        if (!Schema::hasTable('contract_parties')) {
            return; // migration not yet applied
        }

        try {
            DB::transaction(function () use ($contract) {
                // Build the desired set: buyer + every JSON party.
                $desired = [];
                if ($contract->buyer_company_id) {
                    $desired[] = [
                        'company_id' => (int) $contract->buyer_company_id,
                        'role'       => 'buyer',
                    ];
                }
                foreach ((array) ($contract->parties ?? []) as $party) {
                    $cid  = $party['company_id'] ?? null;
                    $role = $party['role'] ?? null;
                    if (!$cid || !$role) {
                        continue;
                    }
                    // Skip the buyer-on-buyer dedupe so we don't insert
                    // it twice when it appears in BOTH columns.
                    if ($role === 'buyer' && (int) $cid === (int) $contract->buyer_company_id) {
                        continue;
                    }
                    $desired[] = [
                        'company_id' => (int) $cid,
                        'role'       => $role,
                    ];
                }

                // Wipe the existing rows for this contract and insert
                // the fresh set. The unique index on
                // (contract_id, company_id, role) protects us from
                // accidental duplicates inside `$desired`.
                ContractParty::where('contract_id', $contract->id)->delete();

                if ($desired === []) {
                    return;
                }

                $now  = now();
                $rows = array_map(fn ($d) => array_merge($d, [
                    'contract_id' => $contract->id,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ]), $desired);

                DB::table('contract_parties')->insertOrIgnore($rows);
            });
        } catch (\Throwable $e) {
            // Junction sync must NEVER block the underlying contract
            // mutation. Log and move on — the JSON column is still
            // canonical so the legacy whereJsonContains path keeps
            // working as a fallback until every callsite migrates.
            \Log::warning('ContractObserver::syncJunction failed', [
                'contract_id' => $contract->id,
                'error'       => $e->getMessage(),
            ]);
        }
    }

    private function fanOutCreatedNotification(Contract $contract): void
    {
        try {
            $partyCompanyIds = collect($contract->parties ?? [])
                ->pluck('company_id')
                ->push($contract->buyer_company_id)
                ->filter()
                ->unique()
                ->values()
                ->all();

            if (empty($partyCompanyIds)) {
                return;
            }

            SendContractNotificationsJob::dispatch(
                companyIds: $partyCompanyIds,
                notification: new ContractCreatedNotification($contract),
                excludeCompanyId: null,
            );
        } catch (\Throwable $e) {
            \Log::warning('ContractObserver::created notification failed', [
                'contract_id' => $contract->id,
                'error'       => $e->getMessage(),
            ]);
        }
    }

    /**
     * The "you need to sign this" prompt is a separate notification
     * from "a contract was created" because it carries the urgency
     * line ("expires in N days") and the explicit Sign CTA. It only
     * fires when the contract starts in PENDING_SIGNATURES — contracts
     * that need internal approval first will fire this once they
     * graduate to PENDING_SIGNATURES via ContractApprovalService.
     */
    private function fanOutSignatureRequestedNotification(Contract $contract): void
    {
        try {
            $status = $contract->status instanceof \BackedEnum ? $contract->status->value : (string) $contract->status;
            if ($status !== 'pending_signatures' && $status !== 'PENDING_SIGNATURES') {
                return;
            }

            $partyCompanyIds = collect($contract->parties ?? [])
                ->pluck('company_id')
                ->push($contract->buyer_company_id)
                ->filter()
                ->unique()
                ->values()
                ->all();

            if (empty($partyCompanyIds)) {
                return;
            }

            SendContractNotificationsJob::dispatch(
                companyIds: $partyCompanyIds,
                notification: new ContractSignatureRequestedNotification($contract),
                excludeCompanyId: null,
            );
        } catch (\Throwable $e) {
            \Log::warning('ContractObserver signature-requested notification failed', [
                'contract_id' => $contract->id,
                'error'       => $e->getMessage(),
            ]);
        }
    }
}
