# 02A User Models and Identity Profiles Smoke Test

Run after importing:

```text
database/02A_user_models_identity_profiles.sql
```

## 1. Health check

Open:

```text
https://microgifter.com/api/health.php
```

Expected:

```json
{"ok":true,"message":"OK","data":{"service":"microgifter","runtime":"hostgator-compatible","database":"connected"}}
```

## 2. Confirm user models seeded

Run in phpMyAdmin:

```sql
SELECT code, name, is_assignable, requires_approval, default_status
FROM user_models
ORDER BY sort_order;
```

Expected codes:

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

## 3. Confirm existing users have customer model

Run:

```sql
SELECT u.id, u.email, um.code, uma.status
FROM users u
JOIN user_model_assignments uma ON uma.user_id = u.id
JOIN user_models um ON um.id = uma.user_model_id
WHERE um.code = 'customer'
ORDER BY u.id;
```

Expected:

```text
Every existing user appears once with customer / active.
```

## 4. Confirm no accidental admin escalation

Run:

```sql
SELECT u.id, u.email, r.slug
FROM users u
JOIN user_roles ur ON ur.user_id = u.id
JOIN roles r ON r.id = ur.role_id
WHERE r.slug IN ('admin', 'super_admin')
ORDER BY u.id;
```

Expected:

```text
Only intended admin/super_admin users appear.
```

## 5. Confirm profile tables exist

Run:

```sql
SHOW TABLES LIKE '%profiles';
```

Expected to include:

```text
creator_profiles
merchant_profiles
moderator_profiles
vendor_manager_profiles
marketing_affiliate_profiles
trader_profiles
```

## 6. Existing app still works

Test:

```text
/signup.php
/signin.php
/account.php
/agent.php
/build.php
```

Expected:

```text
No regressions from Stage 1.
```

## 7. Pass/fail rule

02A is considered imported successfully when:

```text
health passes
all user models are seeded
existing users have active customer model
no new user gets admin/super_admin accidentally
Stage 1 app routes still work
```
