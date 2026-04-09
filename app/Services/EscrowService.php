<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Models\Company;
use App\Models\Contract;
use App\Models\EscrowAccount;
use App\Models\EscrowRelease;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\EscrowEventNotification;
use App\Services\Escrow\BankPartnerException;
use App\Services\Escrow\BankPartnerFactory;
use App\Services\Escrow\BankPartnerInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Phase 3 — high-level escrow orchestration. Sits between the controllers
 * (web + api) and the bank partner adapters. Every state change goes
 * through this service so the audit trail (escrow_releases) stays in sync
 * with the in-bank balance and the linked Payment rows.
 *
 * Pattern: every method either succeeds and returns the model, or throws
 * a BankPartnerException with a human-readable message. The controller
 * catches the exception and surfaces it as a flash error.
 */
class EscrowService
{
    public function __construct(
        private readonly BankPartnerFactory $factory,
    ) {}

    /**
     * Activate escrow on a signed contract. Idempotent: calling it twice
     * returns the existing account instead of opening a second one.
     *
     * Steps:
     *   1. Asks the bank to open an account.
     *   2. Persists the EscrowAccount row.
     *   3. Stamps `contracts.escrow_account_id` so the contract show page
     *      can render the escrow panel without a second query.
     */
    public function activate(Contract $contract): EscrowAccount
    {
        if ($contract->escrow_account_id) {
            return $contract->escrowAccount ?? EscrowAccount::findOrFail($contract->escrow_account_id);
        }

        $partnerKey = $this->factory->defaultKey();
        $partner    = $this->factory->make($partnerKey);

        return DB::transaction(function () use ($contract, $partner, $partnerKey) {
            $payload = $partner->openAccount($contract);

            $account = EscrowAccount::create([
                'contract_id'         => $contract->id,
                'bank_partner'        => $partnerKey,
                'external_account_id' => $payload['external_account_id'] ?? null,
                'currency'            => $payload['currency'] ?? ($contract->currency ?? 'AED'),
                'status'              => EscrowAccount::STATUS_PENDING,
                'metadata'            => $payload['metadata'] ?? null,
            ]);

            $contract->update(['escrow_account_id' => $account->id]);

            $this->notifyParties($contract, 'activated', null);

            return $account->fresh();
        });
    }

    /**
     * Buyer deposits funds INTO the escrow account. Records both the bank
     * call and the ledger entry. The account flips from pending → active
     * the first time funds land.
     */
    public function deposit(EscrowAccount $account, float $amount, string $currency, ?User $user = null): EscrowRelease
    {
        $this->guardAmount($amount);
        $partner = $this->resolvePartner($account);

        return DB::transaction(function () use ($account, $amount, $currency, $user, $partner) {
            $bankResponse = $partner->deposit($account, $amount, $currency);

            $release = EscrowRelease::create([
                'escrow_account_id'    => $account->id,
                'type'                 => EscrowRelease::TYPE_DEPOSIT,
                'amount'               => $amount,
                'currency'             => $currency,
                'milestone'            => null,
                'triggered_by'         => EscrowRelease::TRIGGER_MANUAL,
                'triggered_by_user_id' => $user?->id,
                'bank_reference'       => $bankResponse['reference'] ?? null,
                'notes'                => 'Buyer deposit',
                'recorded_at'          => now(),
            ]);

            // Banks that settle synchronously (mock + most card-funded
            // wires) credit the account immediately. Banks that settle
            // async leave the row in 'pending' and the webhook flips it
            // when the wire actually clears.
            if (($bankResponse['status'] ?? 'completed') === 'completed') {
                $account->increment('total_deposited', $amount);
                if ($account->status === EscrowAccount::STATUS_PENDING) {
                    $account->update([
                        'status'       => EscrowAccount::STATUS_ACTIVE,
                        'activated_at' => now(),
                    ]);
                }
            }

            $this->notifyParties($account->contract, 'deposit', $amount);

            return $release;
        });
    }

    /**
     * Release funds FROM the escrow account TO the supplier — the core
     * Phase 3 operation. Optionally links the release to a Payment row so
     * the milestone is marked completed in one transaction.
     *
     * @param string $trigger one of EscrowRelease::TRIGGER_*
     */
    public function release(
        EscrowAccount $account,
        float $amount,
        string $currency,
        ?string $milestone = null,
        ?Payment $payment = null,
        string $trigger = EscrowRelease::TRIGGER_MANUAL,
        ?User $user = null,
        ?string $notes = null,
    ): EscrowRelease {
        $this->guardAmount($amount);

        if (!$account->isActive()) {
            throw new BankPartnerException('Escrow account is not active.');
        }

        // Phase Hardening — UAE Federal Decree-Law 50/2022 Article 5
        // requires both parties to hold a valid trade license at the
        // moment value moves. Re-check on every release because a
        // license can expire after the contract was signed but before
        // the escrow drains.
        $this->assertContractPartiesLicensed($account->contract);

        $partner = $this->resolvePartner($account);

        return DB::transaction(function () use ($account, $amount, $currency, $milestone, $payment, $trigger, $user, $notes, $partner) {
            // Concurrency safety: lock the account row so two simultaneous
            // releases (e.g. cron sweeper + manual click) cannot both pass
            // the balance check on the same available balance and overdraw
            // the account. The check + the increment + the status flip
            // all run against this locked row.
            $locked = EscrowAccount::whereKey($account->id)->lockForUpdate()->first();
            if (!$locked) {
                throw new BankPartnerException('Escrow account no longer exists.');
            }

            if ($amount > $locked->availableBalance() + 0.01) {
                throw new BankPartnerException(\sprintf(
                    'Insufficient escrow balance: requested %.2f %s, available %.2f.',
                    $amount,
                    $currency,
                    $locked->availableBalance(),
                ));
            }

            $bankResponse = $partner->release($locked, $amount, $currency, $milestone ?? 'milestone');

            $release = EscrowRelease::create([
                'escrow_account_id'    => $locked->id,
                'payment_id'           => $payment?->id,
                'type'                 => EscrowRelease::TYPE_RELEASE,
                'amount'               => $amount,
                'currency'             => $currency,
                'milestone'            => $milestone,
                'triggered_by'         => $trigger,
                'triggered_by_user_id' => $user?->id,
                'bank_reference'       => $bankResponse['reference'] ?? null,
                'notes'                => $notes,
                'recorded_at'          => now(),
            ]);

            $locked->increment('total_released', $amount);

            // Mark the linked Payment row complete so the contract show
            // page renders it as paid and the supplier dashboard reflects
            // the cash inflow. Skip if the payment is already completed.
            if ($payment && $payment->status !== PaymentStatus::COMPLETED) {
                $payment->update([
                    'status'             => PaymentStatus::COMPLETED,
                    'escrow_release_id'  => $release->id,
                    'payment_gateway'    => 'escrow',
                    'gateway_payment_id' => $bankResponse['reference'] ?? null,
                    'approved_at'        => $payment->approved_at ?? now(),
                ]);
            }

            // Auto-close the account once everything has been released.
            if ($locked->fresh()->availableBalance() <= 0.01 && (float) $locked->total_deposited > 0) {
                $locked->update([
                    'status'    => EscrowAccount::STATUS_CLOSED,
                    'closed_at' => now(),
                ]);
            }

            $this->notifyParties($locked->contract, 'release', $amount);

            return $release;
        });
    }

    /**
     * Refund funds back to the buyer (dispute resolved in their favour or
     * cancellation before delivery). Drains the same balance as a release,
     * but the bank uses a different rail and the account flips to
     * REFUNDED instead of CLOSED so the audit trail can distinguish them.
     *
     * Concurrency safety: the balance check + the increment must run under
     * a row lock so two simultaneous refunds can't both pass the check on
     * the same available balance and overdraw the account. We re-fetch the
     * account inside the transaction with lockForUpdate() and re-evaluate
     * availableBalance() against the locked row.
     */
    public function refund(EscrowAccount $account, float $amount, string $currency, string $reason, ?User $user = null): EscrowRelease
    {
        $this->guardAmount($amount);

        $partner = $this->resolvePartner($account);

        return DB::transaction(function () use ($account, $amount, $currency, $reason, $user, $partner) {
            // Pessimistic row lock — blocks any concurrent release/refund
            // on the same account until this transaction commits.
            $locked = EscrowAccount::whereKey($account->id)->lockForUpdate()->first();
            if (!$locked) {
                throw new BankPartnerException('Escrow account no longer exists.');
            }

            if ($amount > $locked->availableBalance() + 0.01) {
                throw new BankPartnerException('Insufficient escrow balance for refund.');
            }

            $bankResponse = $partner->refund($locked, $amount, $currency, $reason);

            $release = EscrowRelease::create([
                'escrow_account_id'    => $locked->id,
                'type'                 => EscrowRelease::TYPE_REFUND,
                'amount'               => $amount,
                'currency'             => $currency,
                'triggered_by'         => EscrowRelease::TRIGGER_MANUAL,
                'triggered_by_user_id' => $user?->id,
                'bank_reference'       => $bankResponse['reference'] ?? null,
                'notes'                => $reason,
                'recorded_at'          => now(),
            ]);

            $locked->increment('total_released', $amount);

            if ($locked->fresh()->availableBalance() <= 0.01) {
                $locked->update([
                    'status'    => EscrowAccount::STATUS_REFUNDED,
                    'closed_at' => now(),
                ]);
            }

            $this->notifyParties($locked->contract, 'refund', $amount);

            return $release;
        });
    }

    /**
     * Webhook entry-point: a bank notifies us a previously-pending deposit
     * has cleared. Promotes the matching ledger row + tops up the account
     * balance. Idempotent on `confirmed_at`: the first webhook delivery
     * stamps the column and any retry short-circuits without double-
     * crediting the account. The whole credit step runs under a row lock
     * on the EscrowAccount so two concurrent first-time webhooks for
     * different references on the same account can't race.
     */
    public function confirmDepositFromWebhook(string $bankReference, float $amount): ?EscrowRelease
    {
        return DB::transaction(function () use ($bankReference, $amount) {
            // Lock the deposit row first so a concurrent webhook for the
            // same bank_reference blocks here until we've stamped
            // confirmed_at and committed.
            $release = EscrowRelease::where('bank_reference', $bankReference)
                ->where('type', EscrowRelease::TYPE_DEPOSIT)
                ->lockForUpdate()
                ->first();

            if (!$release) {
                return null;
            }

            // Already credited? No-op (idempotency).
            if ($release->confirmed_at !== null) {
                return $release;
            }

            // Lock the account too so the increment + status flip is
            // serialised against any concurrent release/refund.
            $account = EscrowAccount::whereKey($release->escrow_account_id)
                ->lockForUpdate()
                ->first();

            if (!$account) {
                return null;
            }

            $account->increment('total_deposited', $amount);
            if ($account->status === EscrowAccount::STATUS_PENDING) {
                $account->update([
                    'status'       => EscrowAccount::STATUS_ACTIVE,
                    'activated_at' => now(),
                ]);
            }

            $release->update([
                'confirmed_at' => now(),
                'triggered_by' => EscrowRelease::TRIGGER_WEBHOOK,
            ]);

            return $release->fresh();
        });
    }

    /**
     * Resolve which payment milestone (if any) this auto-trigger should
     * release against. Looks at the contract's payment_schedule for the
     * first entry whose `release_condition` matches the trigger and
     * which still has an unpaid Payment row. Used by listeners and the
     * cron sweeper to drive Sprint 13's smart release.
     *
     * @return Payment[]
     */
    public function pendingPaymentsFor(Contract $contract, string $condition): array
    {
        $schedule = $contract->payment_schedule ?? [];
        $matchingMilestoneKeys = [];
        foreach ($schedule as $entry) {
            if (($entry['release_condition'] ?? null) === $condition) {
                $matchingMilestoneKeys[] = strtolower((string) ($entry['milestone'] ?? ''));
            }
        }

        if (empty($matchingMilestoneKeys)) {
            return [];
        }

        // Return any payment whose milestone label contains one of the
        // matching keys AND which hasn't already been marked completed.
        $payments = $contract->payments()
            ->whereNotIn('status', [PaymentStatus::COMPLETED->value, PaymentStatus::REFUNDED->value, PaymentStatus::CANCELLED->value])
            ->get();

        return $payments->filter(function (Payment $payment) use ($matchingMilestoneKeys) {
            $label = strtolower((string) $payment->milestone);
            foreach ($matchingMilestoneKeys as $key) {
                if ($key !== '' && str_contains($label, $key)) {
                    return true;
                }
            }
            return false;
        })->values()->all();
    }

    private function resolvePartner(EscrowAccount $account): BankPartnerInterface
    {
        return $this->factory->make($account->bank_partner ?: 'mock');
    }

    private function guardAmount(float $amount): void
    {
        if ($amount <= 0) {
            throw new BankPartnerException('Amount must be greater than zero.');
        }
    }

    /**
     * Assert that every party of a contract still holds a valid trade
     * license. Same compliance hook as ContractService — reused on
     * every release so an expired license stops a drain instead of
     * letting funds flow to an unlicensed entity. Throws
     * BankPartnerException so the controller's existing exception
     * handler surfaces it as a flash error on the contract page.
     */
    private function assertContractPartiesLicensed(?Contract $contract): void
    {
        if (!$contract) {
            return;
        }

        $partyIds = collect($contract->parties ?? [])
            ->pluck('company_id')
            ->push($contract->buyer_company_id)
            ->filter()
            ->unique()
            ->all();

        if ($partyIds === []) {
            return;
        }

        $companies = Company::whereIn('id', $partyIds)->get()->keyBy('id');
        $missing = [];
        foreach ($partyIds as $cid) {
            $company = $companies->get($cid);
            if ($company && !$company->hasValidTradeLicense()) {
                $missing[] = $company->name;
            }
        }

        if ($missing !== []) {
            throw new BankPartnerException(
                'Cannot release escrow funds — trade license missing, expired or unverified for: '
                . implode(', ', $missing)
                . '. Renew the trade license document before retrying.'
            );
        }
    }

    /**
     * Notify every user belonging to a party of the contract that the
     * escrow account changed state. Buyer + supplier both care for
     * different reasons (visibility into custody of funds), so we send
     * to everyone in both companies.
     */
    private function notifyParties(?Contract $contract, string $action, ?float $amount): void
    {
        if (!$contract) {
            return;
        }

        $partyIds = collect($contract->parties ?? [])
            ->pluck('company_id')
            ->push($contract->buyer_company_id)
            ->filter()
            ->unique()
            ->all();

        if (empty($partyIds)) {
            return;
        }

        $recipients = User::whereIn('company_id', $partyIds)->get();
        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new EscrowEventNotification(
            contractId: $contract->id,
            action: $action,
            amount: $amount,
            currency: $contract->currency ?? 'AED',
        ));
    }
}
