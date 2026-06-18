# Public profile, storefront, and engagement UI foundation

## Scope

This phase replaces the legacy server-rendered profile query with a responsive public experience powered exclusively by the canonical public profile read contract:

`GET /api/public/profile.php?slug=<profile-slug>`

The UI is divided into three independently testable sections:

1. Profile identity, links, custom sections, counts, preview, loading, and unavailable states
2. Published storefront identity, product cards, cart entry, and cursor pagination
3. Viewer-authorized posts, recurring memberships, and wallet-funded profile tipping

## Canonical route

`/profile.php?slug=<profile-slug>`

Owner preview remains available with:

`/profile.php?slug=<profile-slug>&preview=1`

The page does not query profile, storefront, product, post, subscription, or tipping tables directly.

## Section 1 — Profile identity

The identity shell renders:

- cover and avatar with safe fallbacks
- display name, headline, biography, type, and location
- followers, supporters, and published-product counts
- safe external website and profile links
- ordered custom profile sections
- public, unlisted, and owner-preview presentation
- canonical URL and client-side noindex handling
- loading skeleton, retry, missing-profile, and service-error states

Profile content is projected with DOM text nodes rather than HTML string injection.

## Section 2 — Storefront and products

A published storefront renders only when the backend includes the canonical storefront projection. The section includes:

- storefront cover, logo, name, headline, and description
- canonical store link
- featured and standard product cards
- safe product media fallbacks
- value and currency formatting
- product detail links
- authenticated add-to-cart through the existing commerce cart endpoint
- guest sign-in return flow
- cursor-based product pagination with duplicate suppression
- published storefront with no products state

No storefront or product state is inferred separately from the profile API response.

## Section 3 — Posts, subscriptions, and tips

Posts are rendered from the backend-filtered viewer result. The browser does not attempt to recreate social visibility or block rules.

The engagement section includes:

- post type, publication date, headline, body, media, captions, and engagement counts
- cursor-based post pagination
- active membership plans with amount, interval, description, and trial information
- guest sign-in boundaries and owner self-subscription suppression
- subscription creation through the existing subscription endpoint
- payment-confirmation event dispatch when the subscription response includes a client secret
- wallet-funded one-time profile tips through the existing tip endpoint
- amount validation, idempotency keys, success, and failure states
- owner and unavailable-tip suppression

Card-funded tip confirmation remains outside this page; this foundation intentionally exposes the already-complete wallet funding path.

## Shared response runtime

`assets/js/public-profile-runtime.js` wraps the first canonical profile read on this page and publishes one shared `mg:public-profile:data` event. Section controllers consume that response rather than issuing duplicate initial reads.

The runtime expands initial collection limits to six items each. Cursor reads do not republish the initial event, preventing later pages from resetting already-rendered collections.

## Safety

- No direct profile-page SQL
- No private or numeric database identifiers introduced by the UI
- No raw metadata or provider payload rendering
- Safe HTTP, HTTPS, and same-origin relative URL validation
- No `innerHTML`, `insertAdjacentHTML`, `document.write`, or `eval` in profile section controllers
- Bounded six-item initial and pagination requests
- Duplicate product, post, and plan suppression
- Existing CSRF and permission enforcement remains authoritative for mutations
- Guest and owner action boundaries are explicit

## Account integration

The account profile editor continues to load its existing API payload. A small link adapter converts its legacy API preview target to the canonical HTML profile route after the profile slug is loaded.

## Validation

The focused validation workflow covers:

- PHP and JavaScript syntax
- complete ordered schema application
- profile UI PHPUnit contracts
- frontend repository contracts
- complete repository PHPUnit suite
- desktop and mobile Playwright coverage
- identity, error, preview, storefront, pagination, post, membership, tip, guest, and owner states

## Deferred

- Card-tip payment confirmation UI
- Follow and unfollow mutation controls
- Post reactions and comments
- Profile editing redesign
- Moderation controls
- Profile discovery and search
- Storefront and product management UI
