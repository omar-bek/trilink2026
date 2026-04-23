<?php

namespace App\Services\Payments;

use App\Enums\ChequeStatus;
use App\Enums\PaymentStatus;
use App\Models\ChequeEvent;
use App\Models\Payment;
use App\Models\PostdatedCheque;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Orchestrates the post-dated cheque lifecycle. Each transition
 * appends a row to cheque_events and, where it has a financial
 * effect, updates the linked Payment's status.
 */
class ChequeService
{
    public function register(array $data, User $actor): PostdatedCheque
    {
        return DB::transaction(function () use ($data, $actor) {
            $cheque = PostdatedCheque::create(array_merge($data, [
                'status' => ChequeStatus::ISSUED->value,
                'created_by' => $actor->id,
            ]));

            $this->event($cheque, ChequeStatus::ISSUED->value, $actor);

            return $cheque->fresh();
        });
    }

    public function deposit(PostdatedCheque $cheque, User $actor): PostdatedCheque
    {
        $this->assertStatus($cheque, [ChequeStatus::ISSUED]);

        if ($cheque->presentation_date && $cheque->presentation_date->isFuture()) {
            throw new RuntimeException(__('cheques.error_presentation_in_future'));
        }

        $cheque->update([
            'status' => ChequeStatus::DEPOSITED->value,
            'deposited_at' => now(),
        ]);

        $this->event($cheque, ChequeStatus::DEPOSITED->value, $actor);

        return $cheque->fresh();
    }

    public function clear(PostdatedCheque $cheque, User $actor): PostdatedCheque
    {
        $this->assertStatus($cheque, [ChequeStatus::DEPOSITED]);

        return DB::transaction(function () use ($cheque, $actor) {
            $cheque->update([
                'status' => ChequeStatus::CLEARED->value,
                'cleared_at' => now(),
            ]);

            $this->event($cheque, ChequeStatus::CLEARED->value, $actor);

            if ($cheque->payment_id) {
                $payment = Payment::find($cheque->payment_id);
                if ($payment && ! in_array($payment->status instanceof \BackedEnum ? $payment->status->value : (string) $payment->status, [
                    PaymentStatus::COMPLETED->value,
                    PaymentStatus::REFUNDED->value,
                ], true)) {
                    $payment->update([
                        'status' => PaymentStatus::COMPLETED->value,
                        'payment_gateway' => 'cheque',
                        'gateway_payment_id' => $cheque->cheque_number,
                        'settled_at' => now(),
                        'paid_date' => now(),
                    ]);
                }
            }

            return $cheque->fresh();
        });
    }

    /**
     * Mark a deposited cheque as returned (bounced). The linked Payment
     * flips back to APPROVED so finance can chase a replacement.
     */
    public function returnCheque(PostdatedCheque $cheque, User $actor, string $reason): PostdatedCheque
    {
        $this->assertStatus($cheque, [ChequeStatus::DEPOSITED]);

        return DB::transaction(function () use ($cheque, $actor, $reason) {
            $cheque->update([
                'status' => ChequeStatus::RETURNED->value,
                'return_reason' => $reason,
                'returned_at' => now(),
            ]);

            $this->event($cheque, ChequeStatus::RETURNED->value, $actor, ['reason' => $reason]);

            if ($cheque->payment_id) {
                $payment = Payment::find($cheque->payment_id);
                if ($payment) {
                    $payment->update([
                        'status' => PaymentStatus::FAILED->value,
                        'rejection_reason' => 'Cheque returned: '.$reason,
                    ]);
                }
            }

            return $cheque->fresh();
        });
    }

    public function stop(PostdatedCheque $cheque, User $actor, string $reason): PostdatedCheque
    {
        $this->assertStatus($cheque, [ChequeStatus::ISSUED, ChequeStatus::DEPOSITED]);

        $cheque->update([
            'status' => ChequeStatus::STOPPED->value,
            'return_reason' => $reason,
        ]);

        $this->event($cheque, ChequeStatus::STOPPED->value, $actor, ['reason' => $reason]);

        return $cheque->fresh();
    }

    public function replace(PostdatedCheque $cheque, User $actor, array $newChequeData): PostdatedCheque
    {
        return DB::transaction(function () use ($cheque, $actor, $newChequeData) {
            $cheque->update([
                'status' => ChequeStatus::REPLACED->value,
            ]);
            $this->event($cheque, ChequeStatus::REPLACED->value, $actor);

            $replacement = $this->register(array_merge([
                'issuer_company_id' => $cheque->issuer_company_id,
                'beneficiary_company_id' => $cheque->beneficiary_company_id,
                'contract_id' => $cheque->contract_id,
                'payment_id' => $cheque->payment_id,
                'drawer_bank_name' => $cheque->drawer_bank_name,
                'drawer_account_iban' => $cheque->drawer_account_iban,
                'amount' => $cheque->amount,
                'currency' => $cheque->currency,
            ], $newChequeData), $actor);

            $this->event($replacement, ChequeStatus::ISSUED->value, $actor, [
                'replaces_cheque_id' => $cheque->id,
            ]);

            return $replacement;
        });
    }

    /**
     * @param  array<ChequeStatus>  $allowed
     */
    private function assertStatus(PostdatedCheque $cheque, array $allowed): void
    {
        $current = $cheque->status instanceof ChequeStatus ? $cheque->status : ChequeStatus::from((string) $cheque->status);
        if (! in_array($current, $allowed, true)) {
            throw new RuntimeException(__('cheques.error_invalid_transition', [
                'from' => $current->value,
                'allowed' => implode(', ', array_map(fn ($s) => $s->value, $allowed)),
            ]));
        }
    }

    private function event(PostdatedCheque $cheque, string $event, User $actor, array $metadata = []): void
    {
        ChequeEvent::create([
            'postdated_cheque_id' => $cheque->id,
            'event' => $event,
            'actor_user_id' => $actor->id,
            'metadata' => $metadata ?: null,
            'created_at' => now(),
        ]);
    }
}
