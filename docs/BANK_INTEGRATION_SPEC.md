# Bank Integration Spec — Trilink Escrow Partnership

**Purpose.** This document is the technical handover package Trilink sends
to a prospective escrow bank partner (Mashreq NeoBiz, Emirates NBD Trade,
First Abu Dhabi Bank Wholesale, or any equivalent UAE commercial bank)
once the commercial agreement is in flight. Its goal is to shorten the
integration from "six months of emails" to a single kickoff + three
delivery calls by giving the bank's API team every answer up front.

The adapter contract itself already exists —
[BankPartnerInterface](../app/Services/Escrow/BankPartnerInterface.php) —
so onboarding a new bank is a matter of implementing one class that wraps
four HTTP calls. The Mashreq implementation
[MashreqNeoBizPartner](../app/Services/Escrow/MashreqNeoBizPartner.php) is
the reference pattern.

---

## 1. What Trilink needs from the bank

### 1.1 Account primitives
Trilink operates a **many-to-many** trust model: one contract → one
escrow account → many milestone releases. Per signed contract we need
the bank to:

| # | Capability | Called by | Frequency |
|---|------------|-----------|-----------|
| 1 | Open a segregated escrow sub-account against a master-trust account | `openAccount()` | 1× per contract at activation |
| 2 | Accept an inbound wire from the buyer's bank, post to the sub-account | `deposit()` (+ webhook) | 1–4× per contract (by milestone) |
| 3 | Release funds from the sub-account to the supplier IBAN | `release()` | 1–N× per contract |
| 4 | Refund funds from the sub-account back to the buyer IBAN | `refund()` | Rare — dispute outcomes only |

### 1.2 Volumes (Year-1 projection)
- Contracts activated: 200–600 / month
- Average contract value: AED 180,000
- Peak release transactions: ~2,000 / month
- P99 API latency target: < 3 s (UI timeout is 8 s)

### 1.3 Regulatory context
Trilink is a **technology-provider**, not a deposit-taking institution.
The escrow account is legally opened in the **buyer's name** with a
charge in favour of the supplier contingent on milestone completion.
Trilink holds a limited **instruction-only** role (equivalent to SCA
Rulebook Module 5 agency arrangement). No customer funds ever transit
Trilink's own accounts.

---

## 2. Integration surface — what we need from the bank's API

### 2.1 Transport

| Item | Value |
|------|-------|
| Protocol | HTTPS 1.2+ only, TLS certs pinned on Trilink side |
| Format | JSON over HTTPS (no SOAP, no XML) |
| Auth | OAuth 2.0 client-credentials **or** static bearer API key |
| Clock skew tolerance | ≤ 60 s |
| IP allow-list direction | Trilink → Bank: we provide a static /32 CIDR. Bank → Trilink: webhooks come from the bank's published CIDR block, we verify HMAC regardless. |
| Sandbox | Required before go-live — Trilink runs a 14-day replay test against sandbox. |

### 2.2 Endpoints we'd call

The Mashreq adapter maps these one-to-one. Any bank can rename paths —
the adapter only needs `{method, path, body, response shape}`:

| # | Endpoint (suggested) | Method | Body | Response |
|---|----------------------|--------|------|----------|
| 1 | `/accounts` | POST | `{reference, currency, beneficiary_name, remitter_name, expected_amount, callback_url}` | `{account_id, iban?, status}` |
| 2 | `/accounts/{id}/deposits` | POST | `{amount, currency, reference}` | `{transaction_id, status: pending\|completed}` |
| 3 | `/accounts/{id}/releases` | POST | `{amount, currency, milestone, reference, beneficiary_iban}` | `{transaction_id, status}` |
| 4 | `/accounts/{id}/refunds` | POST | `{amount, currency, reason}` | `{transaction_id, status}` |
| 5 | `/accounts/{id}` | GET (nice-to-have) | — | `{balance, status, last_event_at}` |

### 2.3 Webhooks — bank → Trilink

This is where most bank integrations fail. We handle this side already;
bank just needs to POST once per settlement event:

- **Endpoint:** `POST https://<tenant>.trilink.ae/api/webhooks/escrow/{partner}`
- **Payload:** JSON — at minimum `{event, account_id, transaction_id, amount, currency, status, occurred_at}`
- **Signature:** `X-Signature: sha256=<hex-hmac>` over the raw request body using a shared secret that the bank provisions on its side and Trilink stores in `ESCROW_<PARTNER>_WEBHOOK_SECRET`
- **Retries:** bank must retry with exponential backoff on non-2xx for 24 h
- **Idempotency:** Trilink dedupes on `transaction_id`; a replay is always safe

### 2.4 Failure modes Trilink handles (the bank does not need to worry)
- Network timeout → Trilink short-circuits the milestone as `release_failed` and notifies the buyer's finance user
- Partial success → on ambiguous HTTP response, Trilink issues a GET status call (2.2 row 5) before retrying
- KYB / compliance rejection at `openAccount` → surfaced to the buyer's onboarding flow with the bank's error code preserved

---

## 3. Data the bank needs from Trilink at partnership setup

To sign an integration agreement the bank's legal + compliance team
will typically ask for the items below. Trilink already produces each of
these as part of normal operation — no engineering effort needed.

| # | Item | Where it comes from |
|---|------|---------------------|
| 1 | Company trade licence + MoA of Trilink operating entity | Corporate file (legal) |
| 2 | UBO disclosure (≥ 25% beneficial owners) | [beneficial_owners](../app/Models/BeneficialOwner.php) table, exportable as a PDF |
| 3 | AML programme summary | [PaymentAmlService](../app/Services/PaymentAmlService.php) + the privacy policy |
| 4 | Sanctions screening vendor | OpenSanctions today, Refinitiv WC1 in roadmap ([services.sanctions](../config/services.php)) |
| 5 | Information-security certs | To be completed: SOC 2 Type I by Q3 2026, ISO 27001 by Q1 2027 (currently a gap — flag honestly) |
| 6 | Standard SLA terms Trilink commits back to the bank (uptime of our webhook receiver etc.) | This document, §5 |
| 7 | Sample end-to-end contract lifecycle trace | Tenant-ready demo environment — see "design partner onboarding" |

---

## 4. Per-bank notes

### 4.1 Mashreq NeoBiz
- **Why first:** already has a published Open Banking portal with sandbox keys (no NDA required to start building). NeoBiz is the SME-focused brand and has an escrow product for trade finance.
- **Primary contact path:** NeoBiz relationship manager → Open Banking API team. Allow 6–8 weeks from kickoff to production API key.
- **Known gaps:** at time of writing, NeoBiz does not support AED + FCY mixed escrow on one account. Workaround: one sub-account per currency.
- **Status in codebase:** sandbox adapter shipped; config wired under `services.escrow.mashreq`.

### 4.2 Emirates NBD (ENBD Trade / businessONLINE)
- **Why second:** largest trade-finance book in the UAE, best counterparty to offer buyers (brand recognition).
- **API access path:** through businessONLINE Developer Portal (requires existing corporate relationship first — so Trilink must open an operating account with ENBD before API onboarding starts). Allow 10–14 weeks.
- **Known gaps:** webhook story is weaker than Mashreq's — legacy SFTP for settlement confirmations. Our adapter will poll status (2.2 row 5) every 60 s for pending deposits until ENBD publishes webhooks.
- **Status in codebase:** config placeholder under `services.escrow.enbd`; adapter class **not yet written** — use MashreqNeoBizPartner as the template and substitute the endpoint paths.

### 4.3 First Abu Dhabi Bank (FAB Wholesale / FAB eCorp)
- **Why third:** federal-sector and ADGM tenant base — essential for enterprise and government-adjacent buyers.
- **API access path:** FAB Developer Portal exists but most cash-management APIs are still NDA-gated. Sponsor needed from FAB Wholesale Banking. Allow 12–16 weeks.
- **Known gaps:** very strict KYB — expect FAB to ask for SOC 2 Type I before production keys. Park this integration until after SOC 2 work.
- **Status in codebase:** **not yet wired**. When starting, add `'fab'` entry under `services.escrow` and an `FabWholesalePartner` class implementing the same interface.

---

## 5. Trilink's SLA back to the bank

Trilink's webhook receiver commits to:

- **Availability:** 99.9% monthly (scheduled maintenance excluded, announced ≥ 72 h in advance)
- **Median processing time:** 500 ms from receipt to 2xx response
- **Replay tolerance:** accept duplicate `transaction_id` up to 30 days after first delivery
- **Public status page:** `status.trilink.ae` (to be stood up — current gap)

---

## 6. Commercial terms Trilink typically asks the bank for

For reference during negotiation — not prescriptive:

| Item | Target |
|------|--------|
| Account opening fee | Waived for first 500 accounts / month, then banded |
| Per-release fee | 0.10% or AED 25, whichever higher (standard local wire) |
| Monthly minimum | AED 0 for Year 1 |
| FX spread on non-AED releases | Cost + ≤ 25 bp |
| White-label possibility | Desired for enterprise tenants (bank-branded IBAN) |

---

## 7. Implementation checklist for onboarding a new bank

Once the commercial agreement is signed, the engineering work is:

1. **Adapter class.** Copy `MashreqNeoBizPartner` → `<NewBank>Partner`. Swap the four endpoint paths + any auth-header differences. ~1 day.
2. **Config wiring.** Add a new entry under `services.escrow.<key>` mirroring the Mashreq block. ~30 minutes.
3. **Factory registration.** Add a `case '<key>'` branch in [BankPartnerFactory](../app/Services/Escrow/BankPartnerFactory.php). ~15 minutes.
4. **Webhook secret.** Bank generates the HMAC secret on their side; we store it in `ESCROW_<KEY>_WEBHOOK_SECRET`. Shared over secure channel only.
5. **Sandbox replay test.** Run `php artisan escrow:replay <key>` (to be added — Phase 4 tooling) against the bank sandbox for 14 days.
6. **Compliance sign-off.** DPO + CFO review the data flow using the AML + privacy checklist in [UAE_COMPLIANCE_ROADMAP](UAE_COMPLIANCE_ROADMAP.md).
7. **Cutover.** Set `ESCROW_DEFAULT_PROVIDER=<key>` for the tenant. Existing escrow accounts keep their original `bank_partner` column value — cutover affects **new** contracts only.

---

## 8. Open items / gaps to flag to the bank

Be honest about these at kickoff — banks appreciate it and it saves
pain at compliance review:

- [ ] **SOC 2 Type I** — scheduled Q3 2026, not yet held.
- [ ] **ISO 27001** — scheduled Q1 2027.
- [ ] **Status page** (`status.trilink.ae`) — not yet stood up.
- [ ] **Penetration test report** — last report dated 2025-11-14 (Phase 2 hardening). A fresh rotation is due before signing with FAB.
- [ ] **Data-residency commitment** — currently AWS eu-west-1; UAE-only residency (AWS me-central-1) is a Q2 2026 task and will likely be a FAB requirement.

---

## 9. Contact routing inside Trilink

| Question type | Owner |
|---------------|-------|
| Commercial / fee structure | Founder / CFO |
| Compliance, KYB, AML | DPO + compliance lead |
| API spec, webhook secrets | Engineering (escrow squad) |
| Go-live coordination | Head of product |

---

*Last updated: 2026-04-22. Keep this spec in lock-step with
[services.escrow](../config/services.php) and
[BankPartnerInterface](../app/Services/Escrow/BankPartnerInterface.php) —
if you change either, update this document in the same PR.*
