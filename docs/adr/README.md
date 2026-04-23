# Architectural Decision Records (ADRs)

Each file in this directory captures a **single architectural decision** — the context, the alternatives considered, the choice, and the consequences.

ADRs are **append-only**: never edit a shipped decision. Instead, write a
new ADR that supersedes it and update the `Status` field of the old one
(e.g. `Superseded by ADR-008`).

## Index

| # | Title | Status |
|---|-------|--------|
| 001 | Negotiation round cap + expiry | Accepted |
| 002 | Signed acceptance on negotiation outcomes | Accepted |
| 003 | FX lock at payment approval | Accepted |
| 004 | Dual approval for payments ≥ AED 500k | Accepted |
| 005 | Post-dated cheque lifecycle as first-class model | Accepted |

## Template

See `template.md`. Keep ADRs ≤ 400 words — they are decisions, not designs.
