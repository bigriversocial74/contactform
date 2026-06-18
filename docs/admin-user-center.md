# Admin user center

The protected user directory is available at `/admin/users.php` to sessions with the `admin.users.view` permission.

## Current scope

This checkpoint is read-only. It supports:

- Search by email, account name, public profile name, or profile slug
- Account status filtering
- Role filtering
- Email verification filtering
- Bounded cursor pagination
- Safe account, role, and public-profile context

The page does not change account status, roles, sessions, or profile state. Those actions require a separate permission and mutation checkpoint.

## API

The page reads from `GET /api/admin/users.php` and uses the merged bounded user-directory service.
