# Claim Voucher Token Migration Manifest Fix

Release Package Validation reported that `stage_18ac_claim_voucher_tokens.sql` was an unregistered SQL file.

Fix applied:

- Registered `stage_18ac_claim_voucher_tokens.sql` in the canonical migration manifest.
- Ordered it after `stage_18aa_admin_predictive_ops.sql` and before Stage 19 migrations.

This should allow `scripts/validate_migration_manifest.php` to pass the migration ordering check for the first-party QR token ledger migration.
