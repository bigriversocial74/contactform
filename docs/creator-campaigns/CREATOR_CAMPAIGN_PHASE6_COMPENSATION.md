# Creator Campaign Phase 6 — Compensation and Earnings

Phase 6 adds campaign compensation rules and an append-only creator earnings ledger on top of verified deliverables and valid Phase 5 attribution.

## Delivered

- Campaign-owned compensation rules for fixed deliverables, percentage conversions, flat conversions, milestones, and manual-only adjustments.
- Immutable rule versions with content hashes, version numbers, effective dates, integer minor-unit amounts, basis-point rates, caps, and minimum source amounts.
- One active version per rule; prior active versions become immutable superseded records.
- Trusted earning ingestion from verified deliverables and valid attributed purchase, claim, or redemption events.
- Campaign-scoped idempotency keys and source/rule-version hashes.
- Append-only earning, adjustment, and reversal events.
- One reversal per original event.
- Agreement-version and rule-version traceability on every earning.
- Merchant compensation workspace and Creator earnings workspace.
- Merchant permissions and Creator ownership-scoped read access.

## Boundaries

Phase 6 does not create campaign budget ledgers, holds, funding reservations, payout execution, disputes, tax reporting, or MCP execution. Those remain later phases.

## SQL

`database/20260722_creator_campaign_compensation_earnings_v6_single_install.sql`
