<?php

namespace App\Services\Escrow;

use App\Models\Contract;
use App\Models\EscrowAccount;
use Illuminate\Support\Str;

/**
 * Default in-memory bank partner. Returns deterministic, plausible-looking
 * responses without ever talking to a real bank, so demos, tests, and the
 * "no env config yet" first-run experience all just work.
 *
 * Production tenants override the BankPartnerInterface binding in
 * AppServiceProvider with a real adapter (MashreqNeoBizPartner or
 * EmiratesNbdTradePartner) once the partnership is signed.
 */
class MockBankPartner implements BankPartnerInterface
{
    public function openAccount(Contract $contract): array
    {
        return [
            'external_account_id' => 'MOCK-ESC-' . strtoupper(Str::random(10)),
            'currency'            => $contract->currency ?? 'AED',
            'metadata'            => [
                'provider'    => 'mock',
                'opened_at'   => now()->toIso8601String(),
                'contract_id' => $contract->id,
            ],
        ];
    }

    public function deposit(EscrowAccount $account, float $amount, string $currency): array
    {
        $this->guardCurrency($account, $currency);

        return [
            'reference' => 'MOCK-DEP-' . strtoupper(Str::random(12)),
            // The mock settles synchronously — no webhook round-trip required.
            // Real banks usually return 'pending' here and fire a webhook
            // a few seconds later, which the EscrowWebhookController handles.
            'status'    => 'completed',
        ];
    }

    public function release(EscrowAccount $account, float $amount, string $currency, string $milestone): array
    {
        $this->guardCurrency($account, $currency);

        if ($amount > $account->availableBalance() + 0.01) {
            throw new BankPartnerException(sprintf(
                'Insufficient escrow balance: requested %.2f %s, available %.2f.',
                $amount,
                $currency,
                $account->availableBalance(),
            ));
        }

        return [
            'reference' => 'MOCK-REL-' . strtoupper(Str::random(12)),
            'status'    => 'completed',
        ];
    }

    public function refund(EscrowAccount $account, float $amount, string $currency, string $reason): array
    {
        $this->guardCurrency($account, $currency);

        return [
            'reference' => 'MOCK-RFD-' . strtoupper(Str::random(12)),
            'status'    => 'completed',
        ];
    }

    public function key(): string
    {
        return 'mock';
    }

    /**
     * Mixed-currency operations are not supported by the bank — we keep one
     * account per currency. The EscrowService should never construct a
     * mismatch but the mock asserts it as a defensive double-check.
     */
    private function guardCurrency(EscrowAccount $account, string $currency): void
    {
        if (strtoupper($currency) !== strtoupper($account->currency)) {
            throw new BankPartnerException(sprintf(
                'Currency mismatch: account is %s, request is %s.',
                $account->currency,
                $currency,
            ));
        }
    }
}
