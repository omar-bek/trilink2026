<?php

namespace App\Services\Escrow;

/**
 * Resolves the right bank partner adapter for an escrow account. The
 * EscrowAccount row carries the `bank_partner` key chosen at activation
 * time, so subsequent operations on that account always go through the
 * same provider — even if the platform's default has since changed.
 *
 * The factory is intentionally tiny: it only knows about the providers
 * we've registered. Adding a new bank means writing one adapter and
 * adding one match arm here.
 */
class BankPartnerFactory
{
    public function make(string $key): BankPartnerInterface
    {
        return match ($key) {
            'mashreq_neobiz' => new MashreqNeoBizPartner(
                apiKey: config('services.escrow.mashreq.api_key'),
                baseUrl: config('services.escrow.mashreq.base_url') ?: 'https://api.sandbox.mashreqbank.com/neobiz/escrow/v1',
                timeout: (int) config('services.escrow.mashreq.timeout', 12),
            ),
            // 'enbd_trade' will land in a follow-up sprint once the ENBD
            // partnership is signed. Until then any unknown key (including
            // 'mock') falls back to the safe in-memory adapter so escrow
            // demos still work.
            default => new MockBankPartner,
        };
    }

    /**
     * Default partner key for newly-activated escrow accounts. Comes from
     * config so a tenant can switch banks platform-wide without re-deploying.
     */
    public function defaultKey(): string
    {
        return config('services.escrow.default', 'mock');
    }
}
