# Profile Discovery and Search Foundation

## Scope

This phase adds the canonical read-only discovery surface for active public and unlisted profiles. It reuses the existing profile, moderation, user-status, social-block, storefront, catalog-version, follower, and subscription authorities. It does not add a second profile index, moderation state, recommendation store, payment authority, or behavioral tracking system.

## Public API

`GET /api/public/discover.php`

Supported query parameters:

- `q`: display name, slug, headline, location, or profile type
- `type`: exact profile type
- `location`: case-insensitive location fragment
- `category`: published product type, title, or description fragment
- `cursor`: opaque filter-bound cursor
- `limit`: bounded to 1–36, default 18

The response contains:

- `results`: organic search results with bounded cursor pagination
- `sections.featured`: deterministic public-data ranking
- `sections.recent`: recently active profiles
- `sections.storefronts`: profiles with a published storefront or product
- `policy`: explicit organic/curated separation and private-data prohibition

## Eligibility and safety

Every query enforces:

- active user account
- active profile moderation status
- public or unlisted visibility
- bidirectional block exclusions for authenticated viewers
- published product/version requirements for product counts and category filters
- published storefront requirements for storefront signals
- public identifiers and presentation fields only

Private profiles, drafts, hidden profiles, suspended profiles, inactive users, blocked relationships, internal numeric IDs, emails, moderation notes, metadata, provider references, payment data, and private behavioral data are excluded.

Unavailable profiles remain non-enumerating because the existing profile route still returns the canonical not-found response for inaccessible slugs.

## Ranking and pagination

Organic relevance is deterministic:

1. exact slug
2. slug prefix
3. exact display name
4. display-name prefix
5. headline fragment
6. location fragment
7. exact profile type
8. remaining matches

Ties are resolved by public storefront/product score, recent public activity, then public UUID. The cursor snapshots those ordering values and includes a signature of the normalized filters, preventing reuse across different searches.

Search wildcard characters are escaped with an explicit `!` escape character before binding.

## Curated sections

Curated results are marked `result_kind=curated`; organic results are marked `result_kind=organic`.

Featured ranking uses only public storefront presence, published-product count, public follower count, and recent public profile/post activity. Recent and storefront sections use the same eligible profile universe and block rules. No private behavior, wallet, payment, ledger, provider, or moderation-note data participates.

## Caching and abuse controls

Anonymous requests use the canonical database-backed rate limiter at 90 requests per minute per IP and receive a 30-second public cache policy. Authenticated requests use a higher user-bound limit and private no-store caching because block relationships affect results.

Invalid requests, rate-limit activity, query failures, and successful result counts are recorded through existing security/event authorities without logging raw private profile data.

## UI

`/discover.php` provides:

- search, type, location, and category filters
- organic, featured, recently active, and storefront sections
- responsive cards with avatar, headline, location, type, followers, supporters, and published-product count
- canonical `/profile.php?slug=` links
- loading, empty, no-results, error, retry, reset, and load-more states
- safe DOM projection without HTML-string injection

## Validation

Focused validation includes:

- complete ordered schema import
- real-MySQL visibility, block, ranking, wildcard, category, and cursor behavior
- PHPUnit authority and projection contracts
- desktop and mobile Playwright coverage
- frontend contracts
- full repository PHPUnit
- full browser regression workflow
