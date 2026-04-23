# Pull Request

## What
<!-- One sentence: what changes are in this PR? -->

## Why
<!-- The problem / ticket / business driver. Link the issue if applicable. -->

## How
<!-- Brief notes on the approach. Highlight any non-obvious decisions. -->

## Testing
- [ ] Unit tests added / updated (`tests/Unit/`)
- [ ] Feature tests added / updated (`tests/Feature/`)
- [ ] `php artisan test` passes locally
- [ ] Manual smoke test on the affected flow
- [ ] Ran against seeded demo data (where applicable)

## Database
- [ ] Migration is idempotent (can be re-run on a dirty dev DB)
- [ ] Includes rollback (`down()`)
- [ ] New FKs have indexes
- [ ] No schema changes (skip this section)

## Security
- [ ] No new `abort_unless` — authorisation goes through a Policy
- [ ] No raw SQL concatenation / mass-assignment risks
- [ ] PII / secrets not logged
- [ ] Webhook/external calls verify signatures

## Breaking changes
<!-- List any breaking API / contract changes + upgrade notes. -->

## Rollback plan
<!-- If this change goes sideways in production, how do we revert safely? -->

## Screenshots / recordings
<!-- Attach for UI changes. -->
