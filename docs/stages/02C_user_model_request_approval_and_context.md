# 02C Microgifter User Model Request Approval and Context

Status: implementation complete.

## Stage 2 alignment

The Stage 2 source plan is primarily public profiles and identity. It includes creator and merchant mode placeholders, public profile identity, profile settings, profile links, completion scoring, public read endpoints, and admin profile moderation.

02C is an intentional Stage 2 extension that supports that plan by making creator, merchant, and affiliate identity modes requestable and approval-gated before the public profile layer is expanded.

This pass does not build commerce, gifts, checkout, wallet, feed, inbox, tips, subscriptions, products, merchant locations, claim codes, or agent commerce.

## Files added

- includes/user_model_workflows.php
- api/user-models/list.php
- api/user-models/my.php
- api/user-models/request.php
- api/user-models/context.php
- api/admin/user-models/action.php
- api/admin/user-models/pending.php
- database/02C_model_default_roles_seed.sql

## Main behavior

- Users can list available user models.
- Authenticated users can view their active model assignments.
- Authenticated users can request creator, merchant, or marketing_affiliate.
- Admin users can approve, enable, reject, disable, suspend, or revoke user models.
- Users can set an active model context in session.
- Approved model activation can auto-create matching profile rows.
- Matching default role mappings can be applied when model_default_roles contains matching roles.

## Stage 2 deviation log

Original Stage 2 names creator and merchant mode flags. This implementation generalizes those flags into user_model_assignments so future identity modes do not require new boolean columns.

Original Stage 2 does not mention moderator, vendor_manager, marketing_affiliate, trader, admin, or super_admin models. These were added as a forward-compatible identity capability catalog, but they do not activate unrelated product domains.

Original Stage 2 expects profile tables and public profile APIs next. Those remain the next major Stage 2 work.

## Upload instructions

Upload the files listed above.

Then import:

- database/02C_model_default_roles_seed.sql

## Smoke test

- /api/health.php returns database connected.
- /api/user-models/list.php returns all models.
- /api/user-models/my.php returns active customer for a logged-in user.
- POST /api/user-models/request.php with model=creator creates pending request.
- Super admin can view /api/admin/user-models/pending.php.
- Super admin can approve via /api/admin/user-models/action.php.
- Approved creator creates creator profile row.
- /api/user-models/context.php can set active_model to customer or another active model.

## Next recommended pass

02D_microgifter_public_profile_schema_and_settings_api
