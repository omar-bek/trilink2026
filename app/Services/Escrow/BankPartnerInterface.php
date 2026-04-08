<?php

namespace App\Services\Escrow;

use App\Models\Contract;
use App\Models\EscrowAccount;

/**
 * Phase 3 / Sprint 11 / task 3.3 — adapter contract for the bank partners
 * we integrate with for escrow. Each implementation wraps the bank's REST
 * API. The default binding (`MockBankPartner`) keeps demos and tests
 * working without a real account; production tenants override the binding
 * to either `MashreqNeoBizPartner` or `EmiratesNbdTradePartner`.
 *
 * Every method is intentionally simple — open one account, deposit, release,
 * refund. We deliberately avoid leaking bank-specific concepts (KYC docs,
 * AML flags, SWIFT messages) into the interface so swapping providers
 * doesn't ripple through the rest of the codebase.
 */
interface BankPartnerInterface
{
    /**
     * Open a brand-new escrow account at the bank tied to this contract.
     * Returns a normalised payload:
     *
     *   [
     *     'external_account_id' => string,  // bank-side account id
     *     'currency'            => string,  // ISO 4217 (3 chars)
     *     'metadata'            => array,   // anything else the bank gave us
     *   ]
     *
     * Throws BankPartnerException on hard failure (network, validation,
     * KYB rejection). The caller wraps the call in a transaction so the
     * EscrowAccount row is rolled back when the bank refuses.
     */
    public function openAccount(Contract $contract): array;

    /**
     * Initiate (or simulate) a deposit FROM the buyer's bank account INTO
     * the escrow account. Returns:
     *
     *   [
     *     'reference' => string, // bank-side transaction id
     *     'status'    => 'pending'|'completed', // some banks settle async
     *   ]
     *
     * The webhook handler later flips pending → completed when the bank
     * notifies us asynchronously.
     */
    public function deposit(EscrowAccount $account, float $amount, string $currency): array;

    /**
     * Release funds FROM the escrow account TO the supplier. Returns the
     * same shape as deposit().
     */
    public function release(EscrowAccount $account, float $amount, string $currency, string $milestone): array;

    /**
     * Refund funds FROM the escrow account back TO the buyer (dispute
     * resolved in their favour or buyer cancellation before delivery).
     */
    public function refund(EscrowAccount $account, float $amount, string $currency, string $reason): array;

    /**
     * Stable identifier for this bank — 'mock', 'mashreq_neobiz',
     * 'enbd_trade'. Used to populate `escrow_accounts.bank_partner` so we
     * know which adapter to use for follow-up calls.
     */
    public function key(): string;
}
