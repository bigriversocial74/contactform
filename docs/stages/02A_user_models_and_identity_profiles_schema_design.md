# 02A Microgifter User Models and Identity Profiles Schema Design

## Status

Design and schema draft complete.

This stage begins the official Stage 2 identity expansion after Stage 1 auth/admin foundation was smoke verified on HostGator.

## Goal

Add a flexible user model system so one login identity can operate in multiple platform modes without creating multiple user accounts.

## Important product decision

User models are not separate account types.

```text
One user login can have many active models.
```

Supported models:

```text
customer
creator
merchant
moderator
vendor_manager
marketing_affiliate
trader
admin
super_admin
```

## Stage 1 dependency

This design depends on Stage 1 tables and behavior:

```text
users
roles
permissions
user_roles
audit_logs
sessions/auth foundation
super_admin bootstrap
```

## Files added

```text
docs/architecture/user_models_and_identity_profiles_design.md
database/02A_user_models_identity_profiles.sql
docs/stages/02A_user_models_and_identity_profiles_schema_design.md
```

## Tables designed

```text
user_models
user_model_assignments
user_model_events
model_default_roles
creator_profiles
merchant_profiles
moderator_profiles
vendor_manager_profiles
marketing_affiliate_profiles
trader_profiles
```

Customer profile behavior remains on the existing Stage 1 user profile baseline for now. A separate customer profile table can be added later only if needed.

## Default behavior

When the SQL is imported, every existing user receives:

```text
customer model = active
```

New registration should later be updated to assign the customer model automatically at signup. That should be implemented in the next pass after this schema is imported and verified.

## Approval behavior

Recommended defaults:

```text
customer: active by default
creator: pending by request
merchant: pending by request
moderator: admin assigned
vendor_manager: admin assigned
marketing_affiliate: pending by request
trader: admin assigned / restricted until later compliance design
admin: admin assigned
super_admin: bootstrap/owner assigned
```

## Authorization rule

A model being active is not enough by itself.

All protected actions must check:

```text
authenticated user
active required model
required permission
object ownership or scope membership
audit/event needs
```

## Import instructions

After uploading the file, import through phpMyAdmin:

```text
database/02A_user_models_identity_profiles.sql
```

Then run:

```text
/api/health.php
```

Expected:

```json
{"ok":true,"message":"OK","data":{"service":"microgifter","runtime":"hostgator-compatible","database":"connected"}}
```

## Manual verification SQL

Confirm seeded models:

```sql
SELECT code, name, is_assignable, requires_approval, default_status
FROM user_models
ORDER BY sort_order;
```

Confirm users were backfilled as customers:

```sql
SELECT u.id, u.email, um.code, uma.status
FROM users u
JOIN user_model_assignments uma ON uma.user_id = u.id
JOIN user_models um ON um.id = uma.user_model_id
WHERE um.code = 'customer'
ORDER BY u.id;
```

## Next implementation pass

Recommended next pass:

```text
02B_microgifter_user_model_helpers_and_registration_assignment
```

Scope:

```text
add PHP helpers for active user models
assign customer model during registration
expose user models in /api/auth/me.php
add admin-safe model lookup helpers
add smoke checks for customer backfill and new user registration
```
