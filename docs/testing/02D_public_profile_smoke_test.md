# 02D Public Profile Smoke Test

Import first:

- database/02D_public_profiles_schema.sql

## 1. Health

Open:

```text
/api/health.php
```

Expected: database connected.

## 2. Confirm profile tables

```sql
SHOW TABLES LIKE 'public_profile%';
```

Expected:

- public_profiles
- public_profile_links
- public_profile_sections

## 3. Confirm user backfill

```sql
SELECT u.id, u.email, pp.slug, pp.status, pp.completion_score
FROM users u
JOIN public_profiles pp ON pp.user_id = u.id
ORDER BY u.id;
```

Expected: every user has one public profile.

## 4. Private profile read

While logged in:

```text
/api/profiles/me.php
```

Expected: profile payload with slug, display_name, status, links, and sections.

## 5. Public read

Set a profile to active/public through the update API or phpMyAdmin, then open:

```text
/api/public/profile.php?slug=YOUR_SLUG
```

Expected: public profile payload.

## 6. Privacy check

Set visibility to private or status to draft/hidden/suspended.

Expected: public endpoint returns not found.

## 7. No Stage 1 regression

Test:

- signup
- signin
- account
- agent
- build
- user model endpoints
