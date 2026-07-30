# Creator–Merchant Affiliate Commerce Bridge v1

## Purpose

This bridge completes the production path between an accepted Creator Campaign participant and Microgifter commerce:

```text
Creator tracking touch
→ eligible merchant checkout
→ paid order
→ canonical attribution
→ Creator earning
→ campaign budget reservation
→ payout record workflow
```

The separate `marketing_affiliate` user model is not used. This is the merchant-product affiliate path inside Creator Campaigns.

## Checkout capture

When a signed-in customer starts checkout, Microgifter looks for an accepted Creator Campaign touch associated with the browser’s existing Creator tracking session. The checkout must:

- belong to the same merchant workspace;
- contain a product linked to the campaign as primary, featured, or commissionable;
- match the selected product version when the campaign restricts a version;
- not be excluded by the campaign;
- remain inside the source conversion window.

Only the existing HMAC session hash is persisted in order metadata. The raw Creator tracking cookie is never copied into the order, audit record, earning, or payout records.

## Paid-order processing

Payment capture remains the canonical authority. After a payment becomes paid, the bridge runs inside a savepoint so Creator-affiliate processing cannot roll back or invalidate the payment.

The bridge creates or reuses:

1. one immutable `purchase` tracking event per campaign/order;
2. one canonical attribution decision using the configured first-touch or last-touch model;
3. one idempotent Creator earning using the active purchase-attributed compensation rule and accepted agreement version;
4. one campaign-budget reservation when an active matching budget has capacity.

If attribution or commission processing fails, the paid order remains paid. The merchant receives an operational notification and the failure is recorded in the order’s Creator-affiliate context for retry and reconciliation.

If an earning is valid but the campaign budget cannot reserve it, the earning remains recorded as an unpaid obligation and the merchant receives a budget-attention notification.

## Refund reconciliation

Successful refunds create proportional, append-only negative earning adjustments using exact integer arithmetic.

Before payout processing:

- reserved or committed budget obligations are reduced or released;
- draft or approved payouts containing the affected reservation are cancelled and their scheduled items released;
- an immutable payout cancellation event is written.

After payout processing or payment:

- the historical payout record is not silently rewritten;
- a Creator Campaign dispute is opened for operator reconciliation and possible external recovery.

Repeated refund requests and webhook replays reuse the existing adjustment, budget event, dispute, and payout evidence.

## Payout boundary

The bridge does not call Stripe transfers or another payout provider. Creator payouts remain approval-controlled, provider-neutral records. Operators may mark externally confirmed payouts through the existing payout workflow. Automatic provider settlement requires a separately approved financial-provider phase.

## Safety and integrity

- No raw Creator cookie, IP address, user-agent string, or payment credential is stored by this bridge.
- Product and merchant ownership are revalidated.
- Campaign, participant, source, agreement, attribution, compensation rule, budget, and payout records remain authoritative in their existing services.
- Purchase events, earnings, reservations, adjustments, disputes, and payout events use stable idempotency contracts.
- Payment and refund authority remains fail-safe even if Creator-affiliate processing needs reconciliation.
