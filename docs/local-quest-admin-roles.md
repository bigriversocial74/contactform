# Local Quest admin roles

## Purpose

The starter foundation needs a reusable admin permission model before it is cloned into more apps.

The role helper lives at:

```text
examples/local-quest-rewards/admin-roles.php
```

## Role hierarchy

```text
Owner           rank 100
Admin           rank 80
Quest Manager   rank 50
Support         rank 30
Sponsor Viewer  rank 10
```

## Capabilities

### Owner

Full control of:

- installer unlocks
- app settings
- admin users
- credential management
- quests
- users
- wallets
- claims
- reports

### Admin

Can manage:

- quests
- users
- wallets
- claims
- reports

Cannot manage owner-level credentials, installer unlocks, or admin-user creation.

### Quest Manager

Can manage:

- quest creation
- quest editing
- quest controls
- quest performance

### Support

Can view and support:

- users
- wallets
- claims
- account-link issues

### Sponsor Viewer

Read-only sponsor reporting access.

## Helper functions

`admin-roles.php` provides:

```text
lqr_admin_role_map()
lqr_admin_role_rank()
lqr_admin_role_label()
lqr_admin_has_role()
lqr_admin_require_role()
lqr_admin_can_manage_admins()
lqr_admin_can_manage_settings()
lqr_admin_can_manage_quests()
lqr_admin_can_support_users()
lqr_admin_can_view_sponsor_reports()
lqr_admin_role_options()
```

## Owner-only actions

The credential page should require owner role for:

- creating admins
- changing admin status
- creating recovery links for other admins
- editing future app credentials/settings
- installer unlock operations

## Remaining wiring item

The helper is committed and validated. The direct `admin-credentials.php` wiring still needs a final edit when the connector allows the credential-management page to be updated safely.

The intended credential-page wiring is:

```php
require __DIR__ . '/admin-roles.php';
```

Then before owner-only actions:

```php
$currentAdmin = lqr_admin_require($state, $config);
lqr_admin_require_role($currentAdmin, 'owner');
```

The UI should also hide owner-only forms from non-owner roles.
