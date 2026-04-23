# ADR-004: Dual approval for payments ≥ AED 500k

- **Status:** Accepted
- **Date:** 2026-04-22
- **Authors:** @khamis-mubarak

## Context

Single-approver workflows are fine for routine milestone payments but
carry unacceptable fraud risk above a certain ticket size. UAE banks and
enterprise finance policies commonly require two-signer authorisation
on wires above **AED 500,000**.

## Decision

- Per-contract override via `contracts.dual_approval_threshold_aed`.
- Platform default from `config('payments.dual_approval_threshold_aed')`
  (= 500,000).
- Threshold is compared against `amount_in_base` (AED) not raw
  `amount`, so cross-currency payments are evaluated consistently — see
  ADR-003.
- Primary approval writes a `PaymentApproval` row with `role = primary`
  and leaves the payment in **PENDING_APPROVAL**.
- Secondary approval by a **different** user (unique index on
  `(payment_id, approver_id, role)` prevents same-user bypass)
  transitions to **APPROVED** and triggers retention skim + platform
  fee allocation.

## Alternatives considered

- **Single approver with post-hoc audit:** cheaper but reactive. A fraud
  loss is unrecoverable once funds leave the bank.
- **Three signers for anything > AED 1M:** considered for future ADR;
  left out of v1 to keep the common case simple.

## Consequences

- Operational overhead for finance teams (two people must be present for
  large payments) — mitigated by in-app notifications when a primary
  approval is recorded.
- Clear forensic trail: every PaymentApproval row carries the IP, UA,
  and amount snapshot. Disputes become tractable.
