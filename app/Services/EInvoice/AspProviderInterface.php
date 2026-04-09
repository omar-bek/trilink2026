<?php

namespace App\Services\EInvoice;

use App\Models\EInvoiceSubmission;

/**
 * Phase 5 (UAE Compliance Roadmap) — abstraction over a Peppol
 * Accredited Service Provider (ASP).
 *
 * Concrete implementations:
 *
 *   - {@see MockAspProvider}    — local UBL generator + fake clearance,
 *                                 used for tests, demos, and pre-go-live.
 *   - {@see AvalaraAspProvider} — Avalara E-Invoicing API skeleton.
 *   - {@see SovosAspProvider}   — Sovos eInvoicing API skeleton.
 *
 * The dispatcher binds the right provider via config('einvoice.default_provider')
 * — adding a new provider is one entry in config/einvoice.php + a new
 * class. The rest of the pipeline is provider-agnostic.
 *
 * Contract:
 *
 *   - Implementations are STATELESS. Any state lives on the
 *     EInvoiceSubmission row, not on the service. This makes it
 *     trivial to swap providers per request and to test in isolation.
 *
 *   - The `submit()` method MUST update the submission row in place
 *     with the resulting status, ASP-side identifiers, and any
 *     response payload — the caller (EInvoiceDispatcher) trusts
 *     the row state on return.
 *
 *   - Implementations MUST NOT throw on transient failures. Catch
 *     network/timeout errors, mark the row as `failed`, set
 *     `next_retry_at`, and return. The dispatcher's job is to react
 *     to row state, not exceptions.
 *
 *   - The `name()` method returns the provider key (mock | avalara |
 *     sovos | ...) so the dispatcher can stamp it on the submission
 *     row before calling submit() — useful for forensics if a
 *     provider goes down mid-transaction.
 */
interface AspProviderInterface
{
    public function name(): string;

    /**
     * Submit the prepared UBL payload (already on the row in
     * `payload_xml`) to the ASP. Update the row in place with the
     * resulting status + identifiers. Never throws — failures are
     * surfaced via the row state.
     */
    public function submit(EInvoiceSubmission $submission): EInvoiceSubmission;

    /**
     * Pull the latest status from the ASP for an in-flight
     * submission. Used by a periodic poller for providers that don't
     * push acknowledgments via webhook (some smaller ASPs are
     * pull-only). The default implementation in the dispatcher just
     * returns the row unchanged.
     */
    public function fetchStatus(EInvoiceSubmission $submission): EInvoiceSubmission;
}
