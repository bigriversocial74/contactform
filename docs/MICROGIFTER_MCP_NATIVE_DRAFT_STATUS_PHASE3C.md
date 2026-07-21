# Microgifter MCP Native Draft Status Phase 3C

Phase 3C adds read-only round-trip visibility after Phase 3B creates an inactive native Microgifter draft.

## Behavior

- Existing `microgifter.drafts.get` responses include `handoff`.
- Existing `microgifter.drafts.list` items include `handoff`.
- Gift, campaign, reward, and message status comes from the canonical PHP authority.
- `/account-agent-handoffs.php` provides the owner status workspace.
- Unchanged reads do not create duplicate status receipts.

## SQL

No new SQL is required. Status-change evidence uses the existing Microgifter `events` ledger.

## Status classes

`not_created`, `draft`, `review`, `active`, `completed`, `archived`, `missing`, and `unknown`.

## Validation

```bash
vendor/bin/phpunit tests/phpunit/McpNativeDraftStatusPhase3cV1ContractTest.php
php scripts/test_mcp_native_draft_status_phase3c.php
```

## Boundary

Status refreshes cannot edit, publish, send, schedule, purchase, issue, activate, fulfill, charge, or enqueue work. Node receives no database credentials.
