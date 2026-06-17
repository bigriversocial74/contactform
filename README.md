# Microgifter Platform

Official from-scratch Microgifter platform build.

## Current status

Stages 1–10E are merged. Stage 10F is the active architecture, deployment, runtime-confidence, and Action Center readiness reconciliation.

The platform now includes identity and security, products and storefronts, commerce and financial operations, PPPM ownership, entitlements, the Microgift Engine, merchant-location redemption, operational controls, dashboards, outbox processing, and retention jobs.

## Canonical ownership

- Commerce owns order and payment truth.
- PPPM owns permanent issued-unit identity and ownership.
- Entitlements own protected digital access.
- Microgift templates and instances own gift lifecycle behavior.
- Merchant locations and claim codes own local redemption authority.
- The Action Center is a user-facing read model, not an ownership or financial authority.

## Action Center

The customer gift workspace has three folders:

- **INBOX** — received and redeemable gifts currently owned by the user.
- **SENT** — gifts transferred by the user to another recipient.
- **CLAIMED** — gifts successfully verified and redeemed by an authorized merchant location.

See `docs/stages/stage_10f_action_center_state_model.md`.

## Deployment validation

- `php scripts/stage10f_apply.php`
- `php scripts/build_full_upgrade_sql.php`
- `php scripts/validate_stage10f_upgrade.php`
- `php scripts/stage10f_runtime_smoke.php`

Pull requests run syntax checks, clean-install migrations, stage smoke scripts, Stage 10F deployment/runtime validation, security tests, and the complete PHPUnit suite.

## Next stage

After Stage 10F closes, Stage 11 should build the Action Center APIs and UI integration on the approved INBOX / SENT / CLAIMED model and the shared application template.
