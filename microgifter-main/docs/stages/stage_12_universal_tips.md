# Stage 12 — Universal Tips

Stage 12 adds a single tip authority that works across Microgifter profiles, creators, merchants, merchant locations, products, feed posts, redeemed gifts, and completed claims.

## Funding paths

### Wallet-funded

1. The sender and target are authorized.
2. The fee snapshot and velocity controls are recorded.
3. The sender's Stage 7 available wallet balance is checked.
4. One balanced Stage 7 ledger group debits the sender and credits the recipient plus any platform fee.
5. The tip becomes `posted` inside the same transaction.

### Stripe-funded

1. The tip is created in `pending` state with an immutable provider payment reference.
2. The signed payment webhook is recorded through the existing payment webhook authority.
3. Successful settlement posts the same balanced Stage 7 ledger transaction used by wallet-funded tips.
4. Failed settlement marks the tip failed without crediting the recipient.

No raw card data is accepted or stored.

## Target authority

- profile and creator targets resolve to active users;
- merchant targets resolve to active merchant workspaces;
- location targets resolve through active merchant locations and their workspace owner;
- product and post targets resolve through published catalog/feed records;
- gift and claim targets require a completed canonical Microgift redemption.

The tip system does not alter product, post, PPPM, Microgift, claim, redemption, entitlement, or ownership state.

## Lifecycle

`pending → funded → posted`

Terminal outcomes:

- `failed` for unsuccessful external funding;
- `reversed` through the administrative linked-ledger reversal workflow.

## Safety and integrity

- sender-scoped exact-request idempotency;
- self-tipping rejection;
- configurable fee snapshot stored with every tip;
- minimum and maximum tip limits;
- per-sender hourly count and amount velocity limits;
- signed and provider-event-idempotent Stripe webhooks;
- canonical Stage 7 double-entry posting and linked reversals;
- backend Action Center enforcement using `can_tip`, claimed folder, and redeemed state;
- recipient alerts through the existing Stage 5H communications foundation.

## APIs

- `POST /api/tips/create.php`
- `GET /api/tips/index.php?direction=received|sent`
- `POST /api/tips/payment-webhook.php?provider=stripe`
- `POST /api/account/action-center-tip.php`
- `POST /api/admin/tip-reverse.php`

## Deferred to Stage 13

Recurring tips, subscriptions, scheduled support, renewal billing, retry/dunning policy, subscription entitlements, and cancellation/resume workflows remain Stage 13 work.
