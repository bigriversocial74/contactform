# Social Feed and Post Publishing Foundation

## Scope

This phase turns the existing Stage 14 post, social graph, visibility, moderation, engagement, and reporting tables into a complete production-facing publishing and feed workflow. It reuses the merged engagement mutation authority and does not create parallel post, reaction, comment, follow, block, mute, report, subscription, product, Microgift, or moderation state.

## Feed reads

`GET /api/public/feed.php`

Supported modes:

- `discover`: active public and unlisted posts from active public or unlisted profiles
- `following`: posts from the authenticated viewer and profiles they actively follow

Both modes use bounded opaque cursor pagination ordered by post creation time and public UUID. Query-time rules enforce:

- active user and profile status
- active public or unlisted author profile
- published post status
- hidden and removed moderation exclusions
- bidirectional block exclusions
- viewer mute exclusions
- follower, subscriber, and premium visibility through the canonical Stage 14 and Stage 13 authorities

Anonymous discovery reads use a short-lived public cache. Authenticated and following reads use private no-store caching because relationship and subscription state affect the result.

## Public projection

Feed items expose only:

- public post UUID
- bounded headline, body, media, visibility, and timestamps
- public author profile UUID, slug, display name, avatar, type, and canonical profile URL
- public product, Microgift, and subscription-plan references when attached
- aggregate engagement counts, current viewer reaction, and saved state
- viewer-safe permissions

Numeric user IDs, internal post IDs, owner emails, metadata, payment state, moderation notes, and provider references do not cross the public boundary.

## Post composer and owner management

`GET /api/social/posts.php?scope=mine`

Returns the authenticated owner’s posts with optional status filtering:

- all
- draft
- published
- archived
- retired

`POST /api/social/posts.php`

Supported actions:

- `create`
- `update`
- `publish`
- `archive`
- `delete`

Delete is an audited soft transition to `retired`; archive transitions to `archived`. Existing post records are updated in place, so visibility changes do not create duplicate posts.

Every mutation requires:

- an authenticated user with `social.posts.create`
- CSRF protection
- bounded database-backed rate limiting
- an idempotency key bound to one canonical request fingerprint
- owner authorization for every product, Microgift, and subscription-plan attachment
- an active public or unlisted profile before publication

Exact mutation replays return the stored response without creating another post or repeating lifecycle transitions.

## Content and attachments

The composer supports:

- text, image, audio, video, greeting-card, multimedia-card, and collaboration post types
- public, unlisted, followers, subscribers, premium, and private visibility
- product attachment by public UUID
- Microgift attachment by public UUID
- subscription-plan attachment by public UUID
- safe external or relative links
- up to eight safe media URLs

Subscriber and premium posts require an active subscription plan owned by the author. Media URLs are restricted to safe HTTP, HTTPS, or same-origin relative URLs. User content is projected into DOM text nodes rather than HTML strings.

## Feed UI

`/feed.php` provides:

- Discover, Following, and My Posts views
- authenticated post composer
- save-draft and publish flows
- owner edit, publish, archive, and delete controls
- loading, empty, sign-in, error, retry, and load-more states
- responsive desktop, tablet, and mobile layouts
- profile, product, and attachment links

The feed reuses the merged engagement APIs for:

- like, love, celebrate, and support reactions
- comment reads and creation
- comment deletion and post-owner hiding
- save and unsave
- copy-link share events
- post reporting
- profile mute and block

## Reporting and safety

`POST /api/social/report.php` now adds:

- visibility and block-aware subject validation
- self-report prevention
- hourly abuse throttling
- duplicate open-report reuse
- transactional moderation flagging
- audit and operational events
- non-enumerating unavailable subject behavior

## Validation

Focused validation covers:

- complete ordered MySQL schema import
- draft creation and publication transitions
- idempotent post creation
- discover and following visibility behavior
- mute, block, and moderation exclusions
- deterministic cursor pagination
- owner status filters
- update, archive, and retired transitions
- subscriber-plan requirements
- media URL safety
- public-safe projections
- PHPUnit authority contracts
- desktop and mobile Playwright workflows
- full repository PHPUnit and browser regression suites
