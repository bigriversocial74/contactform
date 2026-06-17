# Microgifter First-Run Admin Setup

This guide promotes the first trusted account to admin access after Stage 1 registration.

## Principle

Do not create a public “make me admin” endpoint. First admin setup should be done by the project owner directly in the database.

## 1. Register the first account

Use the browser:

```text
/signup.php
```

Create the account that should become the owner/admin.

## 2. Find the user ID

Run:

```sql
SELECT id, email, status, created_at
FROM users
ORDER BY id ASC;
```

Copy the `id` for the account to promote.

## 3. Confirm admin roles exist

Run:

```sql
SELECT id, slug, name
FROM roles
WHERE slug IN ('admin', 'super_admin');
```

## 4. Promote the user

For a normal admin:

```sql
INSERT IGNORE INTO user_roles (user_id, role_id)
SELECT :USER_ID, id
FROM roles
WHERE slug = 'admin';
```

For full owner-level access:

```sql
INSERT IGNORE INTO user_roles (user_id, role_id)
SELECT :USER_ID, id
FROM roles
WHERE slug = 'super_admin';
```

Replace `:USER_ID` with the actual numeric user ID.

## 5. Verify permissions through the app

Sign out and sign back in. Then open:

```text
/account.php
```

Expected:

- role list includes `admin` or `super_admin`
- admin links appear in the account page/header when permission checks pass
- `/api/admin/users.php` returns protected user data only for authorized accounts
- `/api/admin/audit-logs.php` returns audit log data only for authorized accounts

## 6. Audit the promotion manually

Until admin management screens exist, optionally insert a manual audit event:

```sql
INSERT INTO audit_logs (actor_user_id, action, entity_type, entity_id, metadata_json, created_at)
VALUES (:USER_ID, 'admin.promoted.first_run', 'user', :USER_ID, JSON_OBJECT('method', 'manual_sql'), NOW());
```

## 7. Security notes

- Only promote accounts you control.
- Do not email or paste database credentials into chat logs.
- Do not add admin promotion to public registration.
- Keep `super_admin` limited to ownership-level users.
