# ADR-002: Signed acceptance on negotiation outcomes

- **Status:** Accepted
- **Date:** 2026-04-22
- **Authors:** @khamis-mubarak

## Context

Previously, accepting a negotiation round was a single button click that
flipped `round_status = accepted`. There was no forensic evidence of who
accepted, at what time, from which IP, or what exact terms they saw.
Under UAE Electronic Transactions Law 46/2021 a typed signature has the
same binding effect as wet-ink provided we can prove integrity and
attribution. A bare `UPDATE` does not.

## Decision

Accepting a round requires:

1. The user types their full name (**`signature_name`** ≥ 3 chars).
2. The user ticks an acknowledgement checkbox (**`acknowledge = 1`**).
3. The server stamps `signed_by_name`, `signed_at`, `signature_ip`, and
   `signature_hash` = `sha256(bid_id | round_number | offer | signer_id
   | signer_name | timestamp)`.
4. An AuditLog row is written with the before/after payload and the
   signature hash.

## Alternatives considered

- **Full eIDAS-grade digital signature on every acceptance:** overkill
  for a counter-offer (negotiations happen daily), and adds friction that
  kills adoption.
- **Plain button click:** what we had. Insufficient under 46/2021.

## Consequences

- Acceptances are non-repudiable — any dispute can re-compute the hash.
- Slightly more friction (one extra field + checkbox) but the UI is a
  single modal with the agreed terms summarised above the field.
- The hash is stored alongside the row so even a DB restore can be
  validated against the AuditLog chain.
