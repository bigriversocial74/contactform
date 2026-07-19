# Product Bundle Lifecycle and Action Center v5

Phase 5 adds a buyer-owned parent bundle order projection while preserving component-level Microgift lifecycle authority.

## Parent order projection

- `/bundle-orders.php` lists authenticated buyer bundle orders.
- Each order remains one parent purchase with total, recipient, payment, fulfillment, and progress metadata.
- `/bundle-order.php?id={bundle_order_public_id}` shows the full component lifecycle.

## Component lifecycle

- Each bundle order component resolves its linked `microgift_instances` record.
- Component status labels include pending, preparing, delivered, claimed, redeemed, refunded, regifted, and needs attention.
- Components with a linked Microgift expose a deep link into the existing inbox/action-center experience.
- The parent progress percentage is derived from completed component lifecycle states.

## Security

- All reads require authentication.
- Bundle order ownership is enforced by `buyer_user_id`.
- Unexpected exceptions use centralized safe error handling.
- No settlement release, Stripe transfer, refund transfer reversal, or merchant payout execution is introduced.

## SQL

No SQL required. Phase 5 uses the existing Product Bundle Phase 1–3 schema and Microgift lifecycle tables.
