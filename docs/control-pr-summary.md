Control plane PR summary.

Adds api/admin/_controls.php plus PHPUnit coverage for:

- permission boundary
- permitted user state change
- exact replay idempotency
- conflict rejection
- audit write
- schema guard inside active transaction

This is intentionally additive and does not merge automatically.
