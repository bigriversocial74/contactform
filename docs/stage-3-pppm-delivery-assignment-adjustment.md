# Stage 3 PPPM Delivery and Assignment Adjustment

## Status

This package remains part of Stage 3. It completes the operational path between PPPM issuance and merchant redemption. Stage 4 should not begin until assignment, transfer, scheduling, delivery attempts, provider callbacks, and retry state are stable in CI.

## Why the plan changed

The original Stage 3 plan treated gifting as the primary lifecycle. The build clarified that Microgifter is the first product experience on top of PPPM, while PPPM is a source-neutral delivery, stability, audit, and future-demand platform.

Stage 3 therefore expanded to add:

- source-neutral issuance and permanent item identity;
- one PPPM item per individual unit;
- merchant/location verification and redemption;
- PPPM-backed Inbox, Sent, Claimed, item details, and messages;
- recipient assignment and ownership transfer;
- scheduled delivery, attempts, callbacks, and retry foundations.

## Delivery and assignment boundaries

Assignment identifies the intended recipient. Ownership remains separate so an item may be assigned before it is accepted or transferred.

A transfer changes ownership only after recipient acceptance. Pending transfers are cancellable and expire.

A delivery schedule records intent. A delivery record represents one dispatch operation. Delivery attempts record retries and provider results. Provider callbacks are idempotent and authenticated with an HMAC secret.

## Stage 3 completion gate

Stage 3 is complete only when:

1. migrations run from a clean database;
2. assignment and transfer ownership checks pass;
3. scheduled delivery can create a dispatch and attempt;
4. provider callbacks reject invalid signatures and duplicate events safely;
5. failed delivery attempts produce bounded retry state;
6. PPPM item events and snapshots remain append-only;
7. existing gift activity remains compatible;
8. all Security Foundation checks are green.

## Stage 4 carry-forward

Stage 4 should begin with provider adapters and production operations, not another domain-model rewrite. Carry forward:

- email, SMS, push, link, API, and manual provider adapters;
- queue workers and due-schedule processing;
- retry workers, dead-letter handling, and reconciliation;
- recipient acceptance and transfer-link UI;
- delivery preference and consent management;
- provider-specific webhook verification;
- delivery observability, alerts, and dashboards;
- demand forecasting derived from scheduled and delivered PPPM items;
- settlement and merchant reconciliation boundaries.

## Deferred intentionally

The current package stores provider-neutral operations but does not send through a live email, SMS, or push provider. Provider credentials, adapter implementations, queue workers, and production retry execution belong to the Stage 4 operational layer.