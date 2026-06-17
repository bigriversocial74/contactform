# Stage 11F — Action Center reconciliation and repair

Stage 11F closes the Action Center implementation by reconciling the Stage 11 read model against canonical Microgift lifecycle and ownership records.

## Canonical authority

The repair process does not create a new lifecycle or ownership authority. It reads `microgift_instances`, derives the expected sender and recipient projections, and delegates repairs to `mg_action_center_project_lifecycle()`.

## Drift classes

- `missing`: an expected sender or recipient projection does not exist;
- `mismatch`: the projected folder or lifecycle state differs from the canonical instance;
- `orphan`: a projection exists for a user who is no longer an expected sender or recipient.

Orphaned projection rows are archived rather than deleted so historical presentation data is preserved.

## Entry points

- `POST /api/admin/action-center-reconcile.php`
- `php scripts/reconcile_action_center.php`

Both entry points support bounded cursor batches. Audit mode is the default. Repair mode must be explicitly enabled.

### CLI examples

```bash
php scripts/reconcile_action_center.php --limit=100
php scripts/reconcile_action_center.php --repair --limit=100
php scripts/reconcile_action_center.php --repair --all
php scripts/reconcile_action_center.php --after=5000 --limit=250
```

## Safety

- each Microgift is reconciled in its own database transaction;
- failures roll back the current instance without committing a partial repair;
- repeated repair runs are safe because the Stage 11D projector is an upsert authority;
- archived user presentation state is preserved for valid projections;
- the job never mutates canonical Microgift lifecycle or ownership records.
