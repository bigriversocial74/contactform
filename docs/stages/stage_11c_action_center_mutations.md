# Stage 11C — Action Center Read/Unread & Archive/Restore

## Scope

Adds mutation endpoints for marking items read/unread and archiving/restoring inbox items. Does not duplicate inbox, ownership, or lifecycle authority.

## Endpoints

### `POST /api/account/action-center-read.php`
- Parameters: `id` (Action Center public ID)
- Marks an item as read
- Response: `status='read'`

### `POST /api/account/action-center-unread.php`
- Parameters: `id`
- Marks an item as unread
- Response: `status='unread'`

### `POST /api/account/action-center-archive.php`
- Parameters: `id`
- Archives the item
- Response: `status='archived'`

### `POST /api/account/action-center-restore.php`
- Parameters: `id`
- Restores the item from archive
- Response: `status='restored'`

## Notes
- Each mutation only affects the authenticated user's items.
- Archived items do not appear in the `inbox`, `sent`, or `claimed` lists.
- All mutations are idempotent.
- Regression tests ensure counts remain consistent and no lifecycle duplication occurs.