# Admin account management

The protected User Center at `/admin/users.php` now combines account inspection and permission-gated management in the existing user-detail drawer.

Supported operations include account status changes, role assignment/removal, user-model lifecycle management, session visibility, and session revocation.

Every write requires a reason between 8 and 240 characters, confirmation, CSRF validation, and the dedicated permission for that action.

## Permissions

- `admin.users.manage`
- `admin.roles.manage`
- `admin.user_models.manage`
- `admin.sessions.view`
- `admin.sessions.revoke`

## Safeguards

- Operators cannot manage their own account in the admin console.
- Regular administrators cannot manage accounts with admin or super-admin roles.
- Only super administrators may manage elevated roles or system models.
- The last active super administrator cannot be deactivated or stripped of the super-admin role.
- Disabling or returning an account to pending revokes active sessions.
- Successful actions create audit, event, and security records.

## Deployment

Run:

```bash
php scripts/run_migrations.php
```

This applies `database/stage_18k_admin_account_management.sql` through the canonical migration manifest.
