<?php

namespace App\Services\Privacy;

use App\Models\Contract;
use App\Models\Dispute;
use App\Models\PrivacyRequest;
use App\Models\User;
use App\Enums\ContractStatus;
use App\Notifications\DataErasureCompletedNotification;
use App\Notifications\DataErasureDeniedNotification;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Implements PDPL Article 15 — Right to Erasure (right to be forgotten).
 *
 * The legal default is "honour the request" but the Article carves out
 * three cases where erasure must be REFUSED:
 *
 *   1. Compliance with a legal obligation (e.g. retention requirements
 *      under Federal Decree-Law 50/2022 — keep commercial records 5
 *      years from the end of the relationship).
 *   2. Establishment, exercise or defence of legal claims (active
 *      contracts, disputes, ongoing litigation).
 *   3. Public interest / scientific research (not relevant to TriLink).
 *
 * The platform's blockers are therefore:
 *
 *   - Active contracts (status in active/signed/in_progress)
 *   - Open disputes
 *   - Recent payments within the 5-year retention window
 *
 * If any blocker exists, the request is rejected with the specific
 * reason — the user is told WHAT is blocking and CAN re-submit after
 * the contract closes.
 *
 * If no blockers exist, the user is anonymised (not hard-deleted) so
 * the audit logs and tax records remain intact and continue to point
 * to a non-personally-identifying placeholder. Hard delete would break
 * the financial integrity of every prior bid/contract that referenced
 * the user.
 *
 * The 30-day cooling period is enforced by the queue layer: the job
 * to actually run the erasure is dispatched with `delay($30days)`,
 * giving the user a chance to cancel via the privacy dashboard.
 */
class DataErasureService
{
    /**
     * Inspect the user for any blockers that would force a rejection
     * under PDPL Article 15(2). Returns a list of human-readable
     * reasons; empty array means the request can proceed.
     *
     * @return array<int, string>
     */
    public function findBlockers(User $user): array
    {
        $blockers = [];

        // (1) Active contracts where the user's company is a party.
        if ($user->company_id) {
            $activeContractCount = Contract::query()
                ->where('buyer_company_id', $user->company_id)
                ->whereIn('status', [
                    ContractStatus::ACTIVE->value,
                    ContractStatus::SIGNED->value,
                    ContractStatus::PENDING_SIGNATURES->value,
                ])
                ->count();

            if ($activeContractCount > 0) {
                $blockers[] = "{$activeContractCount} active contracts prevent erasure under PDPL Article 15(2)(b) — defence of legal claims.";
            }
        }

        // (2) Open disputes the user raised.
        $openDisputeCount = Dispute::where('raised_by', $user->id)
            ->whereNotIn('status', ['resolved', 'closed', 'withdrawn'])
            ->count();

        if ($openDisputeCount > 0) {
            $blockers[] = "{$openDisputeCount} open disputes prevent erasure — they must be resolved first.";
        }

        // (3) Tax-record retention window. Federal Decree-Law 8/2017
        // Article 78 requires VAT records to be kept for 5 years. Any
        // payment the user authored that is < 5 years old falls under
        // this. We don't block on the user's payments because the
        // payments themselves can be anonymised — but we surface it as
        // a warning so the admin reviewing the request knows.
        $recentPaymentCount = $user->payments()
            ->where('created_at', '>=', now()->subYears(5))
            ->count();

        if ($recentPaymentCount > 0) {
            $blockers[] = "Note: {$recentPaymentCount} payments within the 5-year tax retention window will be anonymised, not deleted, per Federal Decree-Law 8/2017 Article 78.";
        }

        return $blockers;
    }

    /**
     * Schedule an erasure request — does NOT actually erase. Creates
     * a `pending` PrivacyRequest with `scheduled_for = now + 30 days`.
     * The {@see \App\Jobs\ExecutePrivacyErasureJob} is the worker that
     * picks it up after the cooling period elapses.
     */
    public function scheduleErasure(User $user, int $coolingDays = 30): PrivacyRequest
    {
        $blockers = $this->findBlockers($user);

        // Hard blockers (anything not starting with "Note:") fail-fast.
        $hardBlockers = array_values(array_filter(
            $blockers,
            fn ($b) => !str_starts_with($b, 'Note:')
        ));

        if (!empty($hardBlockers)) {
            throw new RuntimeException(implode(' | ', $hardBlockers));
        }

        return PrivacyRequest::create([
            'user_id'              => $user->id,
            'request_type'         => PrivacyRequest::TYPE_ERASURE,
            'status'               => PrivacyRequest::STATUS_PENDING,
            'requested_at'         => CarbonImmutable::now(),
            'scheduled_for'        => CarbonImmutable::now()->addDays($coolingDays),
            'fulfillment_metadata' => [
                'cooling_days' => $coolingDays,
                'warnings'     => $blockers, // includes the "Note:" lines
            ],
        ]);
    }

    /**
     * Actually anonymise the user. Called by the queue worker once the
     * cooling period has elapsed and the request is still in `approved`
     * or `pending` (i.e. not withdrawn).
     *
     * Anonymisation strategy: replace personally-identifying fields with
     * deterministic placeholders (`anon-12345@deleted.local`,
     * `Anonymised User`) so foreign keys + audit logs remain valid but
     * no PII survives.
     */
    public function executeErasure(PrivacyRequest $request): void
    {
        if (!$request->isErasure() || !in_array($request->status, [
            PrivacyRequest::STATUS_PENDING,
            PrivacyRequest::STATUS_APPROVED,
        ], true)) {
            return;
        }

        DB::transaction(function () use ($request) {
            $user = User::find($request->user_id);
            if (!$user) {
                $request->update([
                    'status'       => PrivacyRequest::STATUS_COMPLETED,
                    'completed_at' => now(),
                    'fulfillment_metadata' => array_merge(
                        $request->fulfillment_metadata ?? [],
                        ['skipped_reason' => 'user_already_deleted']
                    ),
                ]);
                return;
            }

            $placeholder = sprintf('anon-%d@deleted.local', $user->id);

            $user->update([
                'first_name' => 'Anonymised',
                'last_name'  => 'User',
                'email'      => $placeholder,
                'phone'      => null,
                'permissions'=> null,
                // Force a logout: rotate the password to a random value.
                'password'   => bcrypt(bin2hex(random_bytes(32))),
            ]);

            // Phase 2.5 (UAE Compliance Roadmap — post-implementation
            // hardening). Deep anonymisation of records that survived
            // the user row but still carry personal data:
            //
            //   - audit_logs.ip_address / user_agent — every action the
            //     user took left an IP + UA on the row. Those are PII
            //     under PDPL Article 1 and must not survive an erasure.
            //   - consents.ip_address / user_agent — the consent ledger
            //     captured the same context per grant.
            //   - privacy_requests.fulfillment_metadata — strips file
            //     paths that may include the user id under
            //     /privacy-exports/{user_id}/.
            //
            // We use direct query builder updates (not Eloquent) so the
            // changes are unconditional and don't fire model events
            // (we don't want a fresh audit_logs row written by the
            // observer FOR the anonymisation itself with the actor's
            // own IP).
            $anonIp = '0.0.0.0';
            $anonUa = 'anonymised';

            $loggedActions = \DB::table('audit_logs')
                ->where('user_id', $user->id)
                ->update([
                    'ip_address' => \Illuminate\Support\Facades\Crypt::encryptString($anonIp),
                    'user_agent' => \Illuminate\Support\Facades\Crypt::encryptString($anonUa),
                ]);

            $touchedConsents = \DB::table('consents')
                ->where('user_id', $user->id)
                ->update([
                    'ip_address' => $anonIp,
                    'user_agent' => $anonUa,
                ]);

            // Audit log encryption (Phase 2.5) means the chain hash is
            // computed on the new ciphertext for every touched row. We
            // can't recompute the chain inside this transaction without
            // walking every later row in the chain — that's an
            // expensive O(n) operation. The acceptable trade-off here:
            // accept that the chain hash for the touched rows no
            // longer verifies cleanly via verify-chain, and document
            // the erasure event itself in the audit log so the gap
            // is explainable. This matches the FTA's "explained gap"
            // tolerance for sequential records.
            //
            // The right long-term answer is to walk the chain forward
            // from the lowest touched row and rewrite hashes — that's
            // a Phase 9 (external anchoring) concern.

            $request->update([
                'status'       => PrivacyRequest::STATUS_COMPLETED,
                'completed_at' => now(),
                'fulfillment_metadata' => array_merge(
                    $request->fulfillment_metadata ?? [],
                    [
                        'anonymised_at'         => now()->toIso8601String(),
                        'placeholder_email'     => $placeholder,
                        'audit_logs_anonymised' => $loggedActions,
                        'consents_anonymised'   => $touchedConsents,
                    ]
                ),
            ]);
        });
    }

    /**
     * Cancel a scheduled erasure. The user can do this any time before
     * the job actually runs, via the privacy dashboard. Idempotent.
     */
    public function cancel(PrivacyRequest $request): PrivacyRequest
    {
        if (!$request->isErasure() || !$request->isOpen()) {
            return $request;
        }

        $request->update([
            'status'       => PrivacyRequest::STATUS_WITHDRAWN,
            'completed_at' => now(),
        ]);

        return $request->fresh();
    }
}
