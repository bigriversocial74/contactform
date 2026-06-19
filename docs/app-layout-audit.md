# Authenticated App Layout Audit

The authenticated page shell is enforced by `scripts/audit_app_layout.php` and `.github/workflows/app-layout-validation.yml`.

## Required contract

Every authenticated entry page must resolve, directly or through local PHP includes, to:

- `includes/header.php` with an app header mode;
- a recognized authenticated app shell;
- an account, agent, or administrative sidebar;
- `includes/footer.php`.

The scanner follows statically resolvable PHP includes so thin entry pages are not falsely flagged when their shell lives in a shared component.

## Repaired pages

The June 2026 audit found and normalized these pages:

- `account/orders.php`
- `admin-ai.php`
- `admin/lifecycle-health.php`
- `admin/moderation.php`
- `admin/ops-queue.php`
- `admin/system-health.php`
- `admin/users.php`
- `checkout-success.php`
- `checkout.php`
- `commerce-operations.php`
- `gift-stream.php`
- `merchant-catalog-operations.php`
- `notifications.php`

Administrative pages use `includes/admin-sidebar.php` and `assets/css/admin-shell.css`. Customer account and checkout pages use `includes/account-sidebar.php`. The immersive gift stream uses the existing agent sidebar.
