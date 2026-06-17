# Stage 8 Handoff from Stage 7

## Next stage

Proceed with Stage 8 for owned assets, entitlements, the customer library, and controlled downloads.

Stage 8 must use the canonical systems already present. It must not create another purchase, payment, ownership, receipt, or balance system.

## Contracts inherited from Stage 7

- Published product versions use the server cart.
- Checkout drafts freeze server-calculated totals.
- Pending orders exist before payment sessions.
- Paid orders finalize receipts.
- Eligible order items continue into PPPM issuance.
- Commerce orders remain the customer obligation source.
- Payment records remain the provider-payment source.
- The grouped ledger remains the internal balance source.
- Refunds create new grouped postings instead of changing history.
- Payment, PPPM, and entitlement identifiers remain separate.

## Ownership rule

Stage 8 should create access from verified paid and issued records. An entitlement or owned asset must reference the permanent order-item or PPPM identity, not a cart row or payment transaction.

## Recommended Stage 8 scope

- entitlements
- owned asset library
- digital downloads
- access grants
- entitlement status and revocation
- download authorization
- controlled file delivery
- download history
- customer-owned item views
- authorized merchant asset visibility

## Required boundaries

- Client requests cannot invent ownership.
- Authorization is checked for every protected asset request.
- Revoked, refunded, disputed, or expired access follows an explicit policy.
- Storage references remain separate from public URLs.
- Raw storage paths are not exposed.
- Access and download events are auditable.
- Entitlement creation is idempotent.
- Stage 8 does not calculate or mutate wallet balances.
- Stage 8 does not create a second order or receipt system.

## Policy dependencies

Stage 8 must define entitlement behavior for full refunds, partial refunds, disputes, gift transfers, completed claims, expiration, and merchant asset removal. Historical records should remain intact while access state changes through explicit events.

## Future Demand carry-forward

Owned assets, repeat access, downloads, and content activity may later become Future Demand signals. Stage 8 should record reliable events but should not implement predictive scoring. That remains Stage 15 and Stage 16 work.

## Opening task

Begin with **Stage 8A — Official Plan Review, Existing Asset and PPPM Inventory, and Gap Analysis**.

Classify each Stage 8 requirement as complete, implemented early, partially complete, misaligned, missing, or intentionally deferred before adding new schema or workflows.
