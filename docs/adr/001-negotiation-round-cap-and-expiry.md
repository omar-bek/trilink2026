# ADR-001: Negotiation round cap + per-round expiry

- **Status:** Accepted
- **Date:** 2026-04-22
- **Authors:** @khamis-mubarak

## Context

The original negotiation flow allowed unlimited counter-offer rounds and
open offers that never expired. In production that meant bids drifted
indefinitely — suppliers left offers "open" for weeks, buyers forgot
they had proposed a counter, and the Bid status stayed ambiguous.

UAE B2B procurement norms expect either an agreement or a decisive close
within a defined window, and the FTA e-invoicing pipeline needs the
acceptance date to land inside the correct VAT return quarter.

## Decision

- Every bid carries a `negotiation_round_cap` (default **5**, override per bid).
- Every counter-offer has an `expires_at` computed by
  `SettlementCalendarService::addBusinessDays(now(), 2)` — honouring UAE
  weekends and federal holidays.
- A cron sweeper (`negotiation:expire-rounds`, every 5 min) auto-rejects
  any open round whose `expires_at` is past and writes an AuditLog row
  attributing the transition to the scheduler.

## Alternatives considered

- **Unlimited rounds with a soft "nudge" email:** keeps flexibility but
  leaves the root problem — indefinite state — untouched.
- **Hard-coded cap of 3:** too aggressive for construction RFQs that
  genuinely need 5-7 rounds to land.

## Consequences

- Bids always reach a terminal state within a predictable window.
- The proforma invoice number can be allocated confidently once an
  acceptance is recorded.
- Suppliers who want more rounds must open a new bid — an explicit
  commercial signal rather than silent drift.
