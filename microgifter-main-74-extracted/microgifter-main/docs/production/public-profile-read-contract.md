# Canonical public-profile read contract

## Scope

This production reconciliation exposes one backend-only read model for a Stage 2 public profile and aggregates later canonical product, storefront, social, subscription, and tip truth. It does not add profile mutations, moderation mutations, discovery, an admin dashboard, or public-profile page UI.

## Endpoint

`GET /api/public/profile.php?slug=<profile-slug>`

The existing Stage 2 public route remains the single canonical endpoint. The reconciliation extends that route through `api/profiles/_public_profile.php`; no parallel profile endpoint is introduced.

Optional parameters:

- `preview=1` for an authenticated owner preview of their own private or draft profile.
- `product_limit`, `post_limit`, and `plan_limit`, each bounded to 1–24.
- `product_cursor`, `post_cursor`, and `plan_cursor` returned by a previous response.

Unavailable private, hidden, suspended, draft, blocked, or inactive-owner profiles return the same non-enumerating 404 response. Administrator preview is intentionally not implemented in this phase.

## Response model

The response contains:

- `profile`: public ID, slug, display name, headline, biography, validated avatar/cover/website URLs, public location label, profile type, visibility, publication timestamp, and owner-preview indicators.
- `links` and `sections`: active rows only, with public IDs and deterministic ordering. Section metadata is never returned.
- `storefront`: published storefront identity and published revision presentation fields only.
- `products`: published products from the canonical storefront ownership and placement relationship, safe public media references, and cursor pagination.
- `posts`: published and unmoderated posts filtered by `mg_social_can_view()` using a bounded preloaded relationship/subscription context.
- `subscription_plans`: active plans with public presentation fields only.
- `tip`: a read-only capability derived through Stage 12, identifying only the public profile target.
- `social_counts`: active, unblocked followers; currently eligible, unblocked supporters; and published storefront products.

No internal numeric IDs, email addresses, phone numbers, provider references, wallet or ledger state, raw metadata, moderation notes, policy JSON, or payment method data are returned.

## Viewer and moderation rules

- Anonymous viewers may read active public and direct-slug unlisted profiles.
- Private and draft profiles require explicit authenticated owner preview.
- Hidden and suspended profiles are unavailable, including to owner preview.
- A block in either direction makes the profile unavailable to that authenticated viewer and removes blocked relationships from aggregate counts.
- Post access remains canonical in Stage 14: owner, follower, subscriber, premium, block, status, and moderation behavior are not copied into the profile service.
- Subscriber access requires a Stage 13 subscription in `trialing`, `active`, or `cancel_pending`, a future paid-period end, and `recovery_status='clear'`.
- Refund, dispute, or chargeback recovery therefore removes subscriber-only content and supporter counts.

## Read safety and caching

The authenticated viewer lookup validates the database-backed session without touching `last_seen_at`. The endpoint does not write audit events, security events, alerts, sessions, payments, tips, subscriptions, or any domain records.

Anonymous public responses use a conservative 60-second public cache and do not create a new session cookie. Unlisted, authenticated, follower-aware, subscriber-aware, blocked, and preview responses are private and non-cacheable. Unlisted responses also emit `X-Robots-Tag: noindex, nofollow`.

## Deferred

- Admin dashboard backend aggregation and UI foundation
- Profile moderation UI
- Public profile/store page HTML, CSS, and JavaScript
- Storefront visual redesign
- Profile editing UI
- Profile discovery/search UI
