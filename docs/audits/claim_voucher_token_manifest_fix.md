# Claim Voucher Token Manifest Fix

Release Package Validation failed after PR #254 because `database/stage_18ac_claim_voucher_tokens.sql` was added but not registered in the canonical migration manifest.

Fix:

- Added `stage_18ac_claim_voucher_tokens.sql` to `config/migrations.php` after `stage_18aa_admin_predictive_ops.sql` and before Stage 19 migrations.

Expected result:

- `scripts/validate_migration_manifest.php` no longer reports the claim voucher token migration as unregistered.
- Clean recovery/release validation can continue past migration-manifest validation.
