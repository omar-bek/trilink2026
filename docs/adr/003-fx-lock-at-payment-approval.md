# ADR-003: FX lock at payment approval

- **Status:** Accepted
- **Date:** 2026-04-22
- **Authors:** @khamis-mubarak

## Context

Payments in currencies other than AED are translated to AED for
reporting (dashboards, credit limits, dual-approval thresholds). The
previous implementation called `ExchangeRate::convert()` at every read,
so the AED-equivalent amount floated with the market between **approval**
and **settlement** — producing inconsistent reporting and occasional
silent violations of the dual-approval threshold (a payment approved at
AED 450k could breach AED 500k a day later without a second signer).

## Decision

The moment a Payment is approved, `FxLockService::lock()` snapshots:

- `fx_rate_snapshot` — the AED-per-unit rate at approval time.
- `fx_base_currency` — always `AED`.
- `fx_locked_at` — the timestamp of the lock.
- `amount_in_base` — `amount * fx_rate_snapshot`, rounded to fils.

Re-locking a payment is a no-op (idempotent) so a second approver cannot
accidentally shift the rate. AED payments get `rate = 1.0` so downstream
consumers never have to branch on currency.

## Alternatives considered

- **Live FX on every read:** what we had. Uncontrolled drift.
- **Daily close snapshot:** fails mid-day approvals that see the wrong
  rate until tomorrow.

## Consequences

- Dual-approval threshold checks are stable post-approval.
- Reports reconcile to the exact AED value that left the buyer's hand.
- If the market moves significantly between approval and settlement, the
  gap is visible on the Payment row (`amount_in_base` vs. live
  conversion of `amount`) — finance can act on it explicitly.
