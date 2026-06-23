# Local Quest replay storage

## Purpose

The starter app needs duplicate protection for two flows:

- protected signed QR completions
- repeated platform delivery callbacks

## SQL tables

The SQL schema now includes:

```text
lqr_signed_code_replays
lqr_webhook_deliveries
```

`lqr_signed_code_replays` stores accepted signed QR replay keys.

`lqr_webhook_deliveries` stores callback delivery IDs and reconciliation status.

## Helper file

```text
examples/local-quest-rewards/replay-storage.php
```

Helper functions:

```text
lqr_sql_replay_seen()
lqr_sql_mark_replay()
lqr_sql_webhook_delivery_seen()
lqr_sql_record_webhook_delivery()
```

## Signed QR replay behavior

`examples/local-quest-rewards/signed-quest-enforcement.php` now uses SQL replay storage first and the translated app-state replay helper as fallback.

## Admin replay log

The signed QR replay log lives at:

```text
examples/local-quest-rewards/admin-signed-replay-log.php
```

It shows the latest accepted signed QR payload replay records, including:

```text
first_seen_at
quest_key
code_type
nonce
replay_key
```

## Remaining work

1. Wire callback delivery dedupe into `webhook.php`.
2. Add an admin delivery log page.
3. Add unmatched delivery review.
4. Add fixture tests for signed QR replay and callback delivery dedupe.
