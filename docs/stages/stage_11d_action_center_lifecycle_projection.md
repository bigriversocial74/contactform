# Stage 11D Action Center Lifecycle Projection

## Scope

Stage 11D completes lifecycle projection behind the existing Inbox, Sent, and Claimed interface. It does not redesign the page or create another source of truth.

The Microgift instance and its claim, redemption, and lifecycle records remain authoritative. The Action Center table remains a user-scoped read model.

## Projection rules

- Issued gifts create or refresh the sender's Sent row.
- A distinct recipient receives an Inbox row.
- Claimed, redeemable, and redeemed gifts appear in Claimed for the recipient.
- A gift that was already claimed remains in Claimed after a later terminal lifecycle state.
- Self-owned gifts use the recipient row rather than replacing it with a Sent row.
- Redemption details project merchant, location, tip eligibility, claim time, and redemption time.
- Archived rows remain archived during lifecycle updates.
- Repeated lifecycle processing updates the same instance-and-user row instead of creating duplicates.

## Covered entry points

- Microgift issuance
- Customer claim
- Customer redemption
- Merchant-location claim and redemption
- Administrative lifecycle actions and exact replays

## Compatibility

The current UI and Stage 11B read APIs continue using the same folder names, counters, search, pagination, and detail responses.

## Database impact

No schema migration is added.

## Deferred

- Stage 11E: Send, Claim, and Message action wiring.
- Stage 11F: reconciliation jobs and end-to-end projection repair coverage.
