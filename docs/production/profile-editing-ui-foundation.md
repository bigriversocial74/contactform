# Profile editing UI foundation

## Scope

This phase replaces the compact account profile form with a complete owner-only profile workspace. It uses the existing Stage 2 profile authority and the public-profile read contract already merged into `main`.

The editor is divided into four sections:

1. Identity and validation
2. Links and custom sections
3. Public-content summaries
4. Media, preview, readiness, and publishing

No duplicate profile schema, storefront authority, product authority, social visibility policy, subscription authority, tipping authority, or media authority is introduced.

## Canonical owner route

`/account.php`

The route loads:

- `includes/account/profile-editor.php`
- `assets/css/profile-editor.css`
- `assets/js/profile-editor.js`

The legacy account controller is not loaded on the profile-editor route, preventing duplicate reads and competing form state.

## Section 1 — Identity editor

The identity editor manages:

- display name
- public slug
- headline
- biography
- location
- website
- profile type
- public, unlisted, or private visibility
- draft, active, or hidden publishing state

Client-side validation mirrors backend length, URL, type, visibility, and slug syntax constraints. Server validation remains authoritative.

The editor provides:

- character counters
- live draft preview
- completion and readiness state
- owner-preview links
- explicit warning before changing the slug of an active profile
- returned-slug handling when a requested address collides
- unsaved-change protection through a persistent dirty-state bar and `beforeunload`

Suspended profiles remain controlled by backend status authority and cannot be published through the editor.

## Section 2 — Links and custom sections

The editor uses the canonical owner endpoints:

- `GET|POST /api/profiles/links.php`
- `GET|POST /api/profiles/sections.php`

Links and sections support:

- add
- edit
- move up
- move down
- activate or deactivate
- remove
- save as a complete ordered collection

Limits are supplied by the backend:

- 12 links
- 20 custom sections

Writes require the authenticated owner session and CSRF validation. Each replacement occurs inside a database transaction and recalculates profile completion.

Allowed link types:

- website
- shop
- portfolio
- social
- newsletter
- custom

Allowed section types:

- about
- story
- highlights
- FAQ
- contact
- custom

## Section 3 — Public-content summaries

`GET /api/profiles/editor-summary.php` provides a read-only management summary for the authenticated owner.

It aggregates existing authorities for:

- storefront existence and status
- total and published products
- total and published posts
- total and active subscription plans
- eligible supporters
- public tip availability
- follower and supporter audience totals
- ready profile-media assets

The summary does not mutate data and does not expose provider state, wallet balances, ledger entries, private subscription reasons, moderation metadata, or raw JSON fields.

Management links point to existing storefront, product, membership, wallet, and public-profile routes rather than adding new management authorities.

## Section 4 — Media and publishing

The owner upload endpoint is:

`GET|POST /api/profiles/media.php`

Uploads are:

- owner scoped
- CSRF protected
- image only
- MIME verified with `finfo`
- dimension verified with `getimagesize`
- size bounded to 5 MB for avatars and 10 MB for covers
- stored under private local storage
- represented by canonical public asset identifiers

The owner preview route is private and no-store. Public delivery through `/api/public/media.php` is allowed only when the asset is attached to an active public or unlisted profile owned by an active user, or is already public through an existing storefront, product, or post authority.

The publishing workspace includes:

- saved-versus-draft comparison
- required and recommended readiness checks
- completion score
- public address
- last-saved timestamp
- save as draft
- publish
- hide
- owner preview
- avatar and cover removal

## API contract

Initial editor state is loaded from:

- `GET /api/profiles/me.php`
- `GET /api/profiles/links.php`
- `GET /api/profiles/sections.php`
- `GET /api/profiles/editor-summary.php`

Writes use:

- `POST /api/profiles/update.php`
- `POST /api/profiles/links.php`
- `POST /api/profiles/sections.php`
- `POST /api/profiles/media.php`

The editor performs safe DOM projection and does not use HTML-string injection for owner content.

## Validation

The focused workflow validates:

- PHP and JavaScript syntax
- complete ordered schema application
- real-MySQL profile creation, identity updates, readiness enforcement, collision-safe slugs, completion scoring, safe media URLs, visibility transitions, and transactional rollback
- focused PHPUnit contracts
- frontend repository contracts
- complete repository PHPUnit suite
- interactive Playwright identity, summary, dirty-state, link, section, media, and publishing behavior
- full browser regression suite

## Deferred

- profile moderation controls
- profile discovery and search
- follow and unfollow controls
- post comments and reactions
- storefront and product management redesign
- advanced image crop and transform tooling
- permanent old-slug redirects
