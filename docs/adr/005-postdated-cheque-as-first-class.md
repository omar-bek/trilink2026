# ADR-005: Post-dated cheque as a first-class model

- **Status:** Accepted
- **Date:** 2026-04-22
- **Authors:** @khamis-mubarak

## Context

Post-dated cheques (PDCs) remain the dominant settlement rail in UAE
B2B trade — construction, commercial leasing, wholesale, auto trade.
Ignoring them meant buyers couldn't use the platform for the 40%+ of
their invoice book settled that way, and we lost the audit trail when
a cheque bounced (Article 401 of the UAE Penal Code — reformed in 2022
— still requires evidentiary retention).

Previously the only way to record a PDC was a free-text note on the
contract.

## Decision

Introduce two tables and a dedicated lifecycle service:

- `postdated_cheques` — one row per cheque with `issuer_company_id`,
  `beneficiary_company_id`, `drawer_bank_name`, `drawer_account_iban`,
  `issue_date`, `presentation_date`, `amount`, `currency`, `status`.
- `cheque_events` — append-only event log per cheque (issued, deposited,
  cleared, returned, stopped, replaced, cancelled).
- `ChequeService` orchestrates every transition, updating the linked
  Payment (`payment_id` FK) atomically: CLEARED → payment COMPLETED,
  RETURNED → payment FAILED.
- `ChequeStatus` enum replaces free-text status strings across the code.
- `PaymentRail::POSTDATED_CHEQUE` joins the rail enum so dashboards show
  PDCs alongside cards and bank wires.

## Alternatives considered

- **Free-text status on the Payment row:** what we had. No event log,
  no bank metadata, no lifecycle guard.
- **Third-party cheque-management SaaS integration:** over-engineered for
  v1; the banks don't yet expose a uniform API.

## Consequences

- PDC-heavy industries can onboard fully.
- Legal/accounting audits now have a queryable trail per cheque.
- Bounce workflow (RETURNED → dispute) is one step, not manual
  reconciliation.
- Future tie-in with the SWIFT / UAEFTS cheque-imaging system (CTS) is
  straightforward because the `drawer_bank_swift` and `image_path`
  columns already exist.
