# Stage 11B — Action Center Read APIs

## Scope

Stage 11B adds stable authenticated read APIs over the canonical Stage 10 Action Center projection. It does not create a second inbox, ownership, gift, claim, redemption, payment, entitlement, or ledger authority.

## Endpoints

### `GET /api/account/action-center.php`

Parameters:

- `folder`: `inbox`, `sent`, or `claimed`; defaults to `inbox`.
- `limit`: 1–100; defaults to 50.
- `q`: optional privacy-safe search across template, gift ID, sender, recipient, location, and display state.
- `cursor`: opaque cursor returned by the previous response.

Response includes:

- folder and normalized query
- all three folder counters
- the current page of items
- `page.limit`, `page.has_more`, and `page.next_cursor`

Ordering is stable: `updated_at DESC, id DESC`. The opaque cursor binds both fields so equal timestamps cannot skip or duplicate rows.

### `GET /api/account/action-center-counts.php`

Returns total and unread counters for Inbox, Sent, and Claimed. Archived presentation rows are excluded.

### `GET /api/account/action-center-detail.php?id=<public-id>`

Returns one visible Action Center projection row belonging to the authenticated user. Cross-user and archived rows return 404.

## Authority and privacy rules

- Every query is scoped by authenticated `user_id`.
- Archived presentation rows are excluded.
- Only public IDs and privacy-safe display fields are returned.
- Claim and redemption credential hashes are never selected.
- Read APIs do not mutate Microgift lifecycle, PPPM ownership, entitlement, commerce, payment, claim, redemption, or ledger records.

## Compatibility

The existing Action Center UI continues using `action-center.php` without requiring changes. Existing consumers still receive `folder`, `counts`, and `items`; Stage 11B adds `query` and `page` fields without removing prior fields.

## Deferred to later Stage 11 phases

- Stage 11C: read/unread and archive/restore mutations.
- Stage 11D: complete lifecycle projection coverage.
- Stage 11E: canonical Send, Claim, and Message action wiring.
- Stage 11F: reconciliation jobs and end-to-end projection tests.
