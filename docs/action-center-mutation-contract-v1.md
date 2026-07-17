# Action Center Mutation Contract v1

## Purpose

Action Center Contract v2 standardizes how Inbox, Sent, Claimed, wallet rewards, and PPPM-linked gifts are read. Mutation Contract v1 standardizes what happens after a customer or merchant action without combining unrelated business operations into one endpoint.

Regift, claim, Follow Up, message, tip, read/unread, archive/restore, voucher preparation, manual voucher redemption, and merchant scanner redemption keep their existing specialized authority. The shared mutation layer owns identity, current-state reconciliation, Contract v2 projection, counts, and browser refresh behavior.

## Standard flow

1. Runtime v4 provides the selected Contract v2 item.
2. The mutation client checks the server-projected capability for the requested action.
3. The specialized endpoint receives only the Action Center public ID, idempotency key, and action-specific input.
4. The specialized endpoint reloads and locks the authoritative wallet, Action Center, PPPM, claim, message, tip, or redemption record.
5. The specialized endpoint performs its existing transaction and audit work.
6. Mutation Contract v1 reloads the current Contract v2 item and merged folder counts.
7. Runtime v4 performs the single list reconciliation.

A browser capability is never final authority. Every specialized endpoint still validates ownership, lifecycle, recipient, merchant, location, permission, CSRF, and idempotency rules.

## Mutation envelope

The reconciliation response contains:

- `mutation_contract_version`
- `contract_version`
- `action`
- `requested_action_item_id`
- `resolved_action_item_id`
- `action_item`
- `counts`
- `remove_action_item_ids`
- `result`
- `synchronized_at`

`action_item` is the current nested Action Center Contract v2 object. It is `null` when the item is no longer visible to the active user, such as after archive or ownership transfer.

## Customer actions

### Regift

`POST /api/account/action-center-send.php`

The endpoint retains PPPM ownership transfer, wallet transfer, delivery history, notification, messaging, Stamp sponsorship, and replay authority. After success, the mutation client resolves the sender's current Sent item when available and removes the former Inbox identity.

### Claim

`POST /api/account/action-center-claim.php`

The canonical claim authority remains responsible for recipient policy, replay, lifecycle, PPPM alignment, and wallet claims. The mutation layer reloads the resulting Claimed item.

### Follow Up

`POST /api/account/action-center-follow-up.php`

Only the latest sender may follow up while the current recipient still owns an open gift. No ownership or delivery mutation is added.

### Message

`POST /api/account/action-center-message.php`

The messaging service remains transfer-scoped and validates the current participant. Wallet reward messages continue to use their merchant notification path.

### Tip

`POST /api/account/action-center-tip.php`

The tips service remains responsible for permission, eligibility, target resolution, funding, fees, velocity, ledger posting, notification, and replay.

### Read, unread, archive, restore

The four state endpoints now return the mutation envelope directly. Standalone wallet rewards are rejected for read/archive operations because those states are not persisted for wallet fallback rows.

## Voucher and merchant redemption

### Voucher preparation

`GET /api/account/action-center-voucher-token.php`

Voucher token issuance remains a read-only preparation operation. It validates the customer-held gift and creates a short-lived signed scan token. It does not change the gift lifecycle.

### Manual customer redemption

`POST /api/account/action-center-voucher-claim.php`

The existing claim-code authority retains rate limiting, signed-token validation, merchant/location binding, redemption cycles, notifications, and lifecycle projection. Its success event is reconciled through Mutation Contract v1 before the existing Claimed confirmation flow continues.

### Merchant scanner redemption

`POST /api/merchant/scanner-claim.php`

The scanner remains merchant-authoritative because it operates under merchant permissions and location binding rather than the customer's session. It retains verify/confirm/redeem behavior, signed-token checks, claim-code authority, idempotent redemption records, notifications, and audit events. Customer Action Center state reflects the merchant transaction on the next canonical read.

## Frontend authority

`assets/js/gift-action-center-actions.js` is the shared mutation client. It:

- Uses Runtime v4's selected Contract v2 item.
- Does not reconstruct currency, instance identity, or capabilities from card text.
- Prevents duplicate in-flight submissions for the same item/action.
- Uses one reconciliation endpoint for specialized actions.
- Uses direct mutation envelopes for read/unread/archive/restore.
- Updates canonical counts.
- Calls Runtime v4 rather than simulating a Refresh-button click.
- Preserves the existing exact-recipient regift confirmation and voucher-claim confirmation flows.

## Compatibility boundary

This phase intentionally keeps the specialized action endpoints and existing database authorities. It does not merge financial, ownership, messaging, claim, or redemption logic into one service. It also keeps legacy response payloads from those endpoints available to their specialized modal runtimes while the shared reconciliation response supplies the current Contract v2 state.

## SQL

No SQL required.
