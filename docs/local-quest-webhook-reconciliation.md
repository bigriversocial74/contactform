# Local Quest webhook reconciliation

## Purpose

The Quest app wallet should not depend only on manual status refresh. Microgifter webhooks should update local wallet and claim state automatically after the webhook signature is verified.

## Files

```text
examples/local-quest-rewards/webhook.php
examples/local-quest-rewards/webhook-reconcile.php
```

## Receiver behavior

`webhook.php` verifies Microgifter webhook signatures with:

```text
X-Microgifter-Timestamp
X-Microgifter-Signature
```

The signature base is:

```text
<timestamp>.<raw request body>
```

When verified, the receiver now calls:

```php
lqr_reconcile_microgifter_webhook($state, $event, $payload, $deliveryId);
```

## Reconciliation behavior

`webhook-reconcile.php` matches local wallet rewards using:

- `reward_id`
- `item_id`
- `pppm_item_id`
- `external_user_id`

When a match is found, it updates local reward state with:

- latest status
- item ID when available
- last webhook event
- last webhook delivery ID
- last webhook timestamp
- last webhook payload

For claim/redemption-style events, it also updates local claim state.

## Supported event mapping foundation

The helper recognizes these event-style statuses:

```text
reward.queued
reward.delivered
reward.viewed_in_app
reward.claimed_in_app
reward.redeem_started
reward.redeem_handoff
reward.redeemed
reward.failed
```

## Remaining production work

1. Add a webhook replay/deduplication table keyed by delivery ID.
2. Add admin webhook event search and reconciliation status UI.
3. Add retries/dead-letter views for failed or unmatched webhooks.
4. Add tests with signed fixture payloads.
5. Reconcile against direct SQL repositories instead of translated app state arrays.
