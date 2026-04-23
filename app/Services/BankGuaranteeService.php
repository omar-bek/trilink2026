<?php

namespace App\Services;

use App\Enums\BankGuaranteeStatus;
use App\Enums\BankGuaranteeType;
use App\Models\BankGuarantee;
use App\Models\BankGuaranteeCall;
use App\Models\BankGuaranteeEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Bank Guarantee lifecycle orchestration. In UAE B2B practice, a BG is a
 * paper instrument issued by a bank that the beneficiary can call on
 * demand if the applicant defaults on the underlying obligation.
 *
 * This service covers the platform-side workflow:
 *  - Applicant (supplier) registers a BG issued in favour of the buyer.
 *  - Buyer validates the advice document (manual for now; SWIFT MT760
 *    auto-validation is a future hook behind VerifyViaBankInterface).
 *  - Either party can extend expiry (issues a new event row).
 *  - Beneficiary can "call" the BG — platform records the claim and
 *    routes it to the issuing bank (stub; real MT761/MT763 messaging
 *    lives in the forthcoming SwiftMessageService).
 *  - Applicant's obligation completes → BG is "returned" and released.
 */
class BankGuaranteeService
{
    public function register(array $data, User $actor): BankGuarantee
    {
        $type = $data['type'] instanceof BankGuaranteeType
            ? $data['type']
            : BankGuaranteeType::from((string) $data['type']);

        return DB::transaction(function () use ($data, $actor, $type) {
            $data['bg_number'] = $data['bg_number'] ?? $this->generateNumber($type);
            $data['status'] = BankGuaranteeStatus::PENDING_ISSUANCE->value;
            $data['created_by'] = $actor->id;
            $data['amount_remaining'] = $data['amount'];
            $data['amount_called'] = 0;

            $bg = BankGuarantee::create($data);

            $this->recordEvent($bg, $actor, 'registered', [
                'type' => $type->value,
                'amount' => (float) $bg->amount,
                'currency' => $bg->currency,
            ]);

            return $bg->fresh();
        });
    }

    /**
     * Mark the BG as formally issued by the bank. The buyer's finance
     * team validates the advice (SWIFT MT760 in production; PDF manual
     * today) and flips the status so the platform treats the BG as live.
     */
    public function activate(BankGuarantee $bg, User $actor): BankGuarantee
    {
        if ($bg->status !== BankGuaranteeStatus::PENDING_ISSUANCE) {
            throw new RuntimeException('Only pending guarantees can be activated.');
        }

        $bg->update([
            'status' => BankGuaranteeStatus::LIVE->value,
            'issued_at' => $bg->issued_at ?? now(),
            'activated_at' => now(),
        ]);

        $this->recordEvent($bg, $actor, 'activated');

        return $bg->fresh();
    }

    /**
     * Beneficiary calls the BG — files a claim for X AED against the
     * issuing bank. The platform records the call and marks the BG
     * CALLED so downstream flows (contract termination, escrow refund)
     * can react. In production this triggers the outbound SWIFT MT765.
     */
    public function call(BankGuarantee $bg, User $actor, float $amount, string $reason, ?string $documentPath = null): BankGuaranteeCall
    {
        if (! $bg->status?->isLive()) {
            throw new RuntimeException('Only live guarantees can be called.');
        }
        if ($actor->company_id !== $bg->beneficiary_company_id) {
            throw new RuntimeException('Only the beneficiary may call this guarantee.');
        }

        $remaining = $bg->remainingLiability();
        if (bccomp((string) $amount, (string) $remaining, 2) > 0) {
            throw new InvalidArgumentException(sprintf(
                'Call amount %.2f exceeds remaining BG liability %.2f.',
                $amount,
                $remaining
            ));
        }

        return DB::transaction(function () use ($bg, $actor, $amount, $reason, $documentPath) {
            $call = BankGuaranteeCall::create([
                'bank_guarantee_id' => $bg->id,
                'called_by_company_id' => $actor->company_id,
                'called_by_user_id' => $actor->id,
                'amount' => $amount,
                'currency' => $bg->currency,
                'reason' => $reason,
                'claim_document_path' => $documentPath,
                'status' => 'submitted',
            ]);

            $bg->update([
                'status' => BankGuaranteeStatus::CALLED->value,
                'amount_called' => (float) $bg->amount_called + $amount,
            ]);

            $this->recordEvent($bg, $actor, 'called', [
                'call_id' => $call->id,
                'amount' => $amount,
            ]);

            return $call;
        });
    }

    /**
     * Reduce an Advance Payment BG as goods are delivered. Only valid for
     * types where reducible() returns true. The reduction lowers the
     * remaining liability but keeps the BG in LIVE state — it's still
     * callable for what remains.
     */
    public function reduce(BankGuarantee $bg, User $actor, float $amount, string $reason): BankGuarantee
    {
        if (! $bg->type?->reducible()) {
            throw new RuntimeException('This guarantee type cannot be reduced.');
        }
        if (! $bg->status?->isLive()) {
            throw new RuntimeException('Only live guarantees can be reduced.');
        }

        $newRemaining = (float) ($bg->amount_remaining ?? $bg->amount) - $amount;
        if ($newRemaining < 0) {
            throw new InvalidArgumentException('Reduction exceeds remaining BG amount.');
        }

        $bg->update([
            'amount_remaining' => $newRemaining,
            'status' => $newRemaining > 0
                ? BankGuaranteeStatus::REDUCED->value
                : BankGuaranteeStatus::RETURNED->value,
            'returned_at' => $newRemaining <= 0 ? now() : $bg->returned_at,
        ]);

        $this->recordEvent($bg, $actor, 'reduced', [
            'amount' => $amount,
            'reason' => $reason,
            'remaining' => $newRemaining,
        ]);

        return $bg->fresh();
    }

    /**
     * Release the BG back to the applicant — the buyer confirms
     * obligation completion. Flips status to RETURNED.
     */
    public function release(BankGuarantee $bg, User $actor, ?string $reason = null): BankGuarantee
    {
        if ($actor->company_id !== $bg->beneficiary_company_id) {
            throw new RuntimeException('Only the beneficiary may release this guarantee.');
        }

        $bg->update([
            'status' => BankGuaranteeStatus::RETURNED->value,
            'returned_at' => now(),
        ]);

        $this->recordEvent($bg, $actor, 'returned', ['reason' => $reason]);

        return $bg->fresh();
    }

    /**
     * Extend expiry. In UAE procurement it's common for the buyer to
     * ask the supplier to extend a BG that's approaching expiry while
     * the contract is still active. The supplier asks the bank, and the
     * bank issues an amendment; the platform records the new date.
     */
    public function extend(BankGuarantee $bg, User $actor, string $newExpiry, ?string $note = null): BankGuarantee
    {
        $new = \Carbon\Carbon::parse($newExpiry);
        if ($new <= $bg->expiry_date) {
            throw new InvalidArgumentException('New expiry must be later than current expiry.');
        }

        $old = $bg->expiry_date?->format('Y-m-d');
        $bg->update(['expiry_date' => $new]);

        $this->recordEvent($bg, $actor, 'extended', [
            'old_expiry' => $old,
            'new_expiry' => $new->format('Y-m-d'),
            'note' => $note,
        ]);

        return $bg->fresh();
    }

    /**
     * Platform-internal identifier. Humans see this number in the UI;
     * the bank's own reference is stored separately.
     */
    public function generateNumber(BankGuaranteeType $type): string
    {
        $prefix = match ($type) {
            BankGuaranteeType::BID_BOND => 'BB',
            BankGuaranteeType::PERFORMANCE_BOND => 'PB',
            BankGuaranteeType::ADVANCE_PAYMENT => 'AP',
            BankGuaranteeType::RETENTION, BankGuaranteeType::WARRANTY => 'RB',
            BankGuaranteeType::LABOR => 'LB',
            default => 'BG',
        };

        return sprintf('%s-%s-%06d', $prefix, date('Y'), BankGuarantee::max('id') + 1);
    }

    private function recordEvent(BankGuarantee $bg, User $actor, string $event, array $metadata = []): BankGuaranteeEvent
    {
        return BankGuaranteeEvent::create([
            'bank_guarantee_id' => $bg->id,
            'actor_user_id' => $actor->id,
            'event' => $event,
            'metadata' => $metadata ?: null,
            'created_at' => now(),
        ]);
    }
}
