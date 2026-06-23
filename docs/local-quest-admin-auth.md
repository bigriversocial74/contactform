# Local Quest admin access hardening

## What was added

Local Quest now has a shared admin credential helper:

```text
examples/local-quest-rewards/admin-auth.php
```

It supports:

- bootstrapped admin records from config
- hashed admin credentials
- admin session tracking
- adding additional admin users
- changing the current admin secret
- enabling/disabling admin users
- one-time recovery tokens stored by hash
- admin activity events

The Admin Credentials page is:

```text
examples/local-quest-rewards/admin-credentials.php
```

It supports:

- admin sign-in
- creating admin users
- changing the current admin secret
- creating one-time recovery links
- enabling/disabling admin users

## Schema extension

SQL extension file:

```text
examples/local-quest-rewards/database/local_quest_admin_auth.sql
```

It adds a recovery-token table and extra admin-user columns for SQL runtime mode.

## Runtime note

The app still defaults to JSON runtime storage. In JSON mode, admin users and recovery tokens live in `data/state.json` under:

```text
admin_users
admin_password_resets
```

When Stage LQ-DB converts runtime storage to MySQL, those records should move into:

```text
lqr_admin_users
lqr_admin_password_resets
lqr_admin_audit_events
```

## Security checklist still needed

Before production use:

1. Disable bootstrap credentials after first admin exists.
2. Use a stored hash instead of a plain config secret.
3. Add CSRF tokens to every admin form.
4. Add login attempt throttling and lockouts.
5. Send recovery links by email instead of showing them in the UI.
6. Add forced rotation for bootstrap credentials.
7. Add role checks for owner-only actions.
8. Move all admin state to SQL runtime storage.
