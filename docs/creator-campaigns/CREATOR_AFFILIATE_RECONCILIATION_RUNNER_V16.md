# Creator Affiliate Reconciliation Runner v16

## Command

From the Microgifter project root:

```bash
php scripts/run_creator_affiliate_reconciliation_v16.php
```

The script is CLI-only and returns JSON. Exit code `0` means all workspace scans completed without detector errors. Exit code `1` means at least one detector or workspace failed. Exit code `2` means another reconciliation run already holds the database advisory lock.

## Recommended schedule

Run hourly from the production scheduler after the v16 migration and code deployment:

```cron
7 * * * * cd /path/to/microgifter && /usr/bin/php scripts/run_creator_affiliate_reconciliation_v16.php >> storage/logs/creator-affiliate-reconciliation.log 2>&1
```

Use the real deployment path and PHP binary. Keep the script outside browser execution and protect the generated log using the existing runtime-directory controls.

## Safety boundary

The runner only:

- reads Creator Campaign commerce, attribution, earning, budget, refund, payout, dispute, and tracking evidence
- creates or updates persistent reconciliation cases
- resolves previously open cases when a complete clean scan no longer detects them
- records a critical scanner case when an individual detector fails

It does not:

- modify an order or refund
- create, adjust, or reverse an earning
- reserve, commit, release, or cancel a budget obligation
- approve, process, pay, cancel, or reverse a payout
- resolve a dispute
- execute a provider transfer
- store payment credentials
- grant MCP authority

## Concurrency

The runner uses the MySQL advisory lock:

`microgifter_creator_affiliate_reconciliation_v16`

Only one scheduled scan can run at a time. The lock is connection-scoped and is released explicitly at completion or automatically when the database connection closes.

## Operator workflow

1. Schedule the runner hourly.
2. Open `/merchant-creator-affiliate-operations.php`.
3. Review critical cases first, followed by high and warning cases.
4. Open the linked operational workspace.
5. Correct or verify the source evidence.
6. Run reconciliation again.
7. Mark a case resolved only after its source lifecycle is correct.

A manually marked resolution will reopen on a later scan if the underlying condition still exists.
