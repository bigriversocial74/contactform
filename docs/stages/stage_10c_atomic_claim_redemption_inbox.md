# Stage 10C — Atomic Claim/Redemption and Inbox Integration

## Purpose

Stage 10C connects the Stage 10B merchant-location authority service to the canonical Stage 9 Microgift and PPPM lifecycle. One transaction now coordinates approval, redemption, claim-code usage, inbox movement, and events.

## Transaction order

1. Begin the transaction.
2. Check the redemption idempotency key.
3. Lock the Microgift instance.
4. Revalidate paid source, ownership, lifecycle state, and expiration.
5. Lock and validate the template merchant.
6. Resolve merchant-location authority and verify the location claim code.
7. Enforce the Microgift location policy.
8. Insert the approved attempt record.
9. Insert the canonical Microgift redemption with location, claim-code, and attempt references.
10. Call `mg_pppm_redeem()`.
11. Mark the Microgift instance redeemed.
12. Increment claim-code usage.
13. Create or move the recipient inbox item to Redeemed and expose the non-financial `can_tip` flag.
14. Append canonical and compatibility events.
15. Commit.

## Failure behavior

A failed transaction rolls back before its normalized failure attempt is recorded. Failed submissions therefore remain auditable without changing Microgift, PPPM, inbox, redemption, or claim-code usage state.

## Inbox read model

`microgift_inbox_items` is unique by Microgift instance and user. It is a read model, not claim authority. Supported states are Received, Claimable, Claimed, Redeemed, Expired, and Revoked.

## Events

Stage 10C emits:

- `gift.claim_attempted`
- `gift.claimed`
- `claim.approved`
- `merchant_location.redemption_completed`
- `inbox.item_moved_to_claimed`
- `psr.redeemed_pending`
- `microgift.redemption_completed`

## Scope boundary

Stage 10D will add the public and merchant-facing claim APIs, operational history reads, rate limits, review escalation, and retryable notification/analytics delivery.
