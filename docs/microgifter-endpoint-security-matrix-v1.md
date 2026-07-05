# Microgifter Endpoint Security Matrix v1

Date: 2026-07-05
Base branch: `integration-from-repair-20260628`
Audit branch: `audit/codebase-review-v1-20260705`

This matrix is a scaffold for endpoint-by-endpoint security review. It is intentionally documentation-only.

---

## Review legend

| Field | Meaning |
|---|---|
| Method | Required HTTP method enforced by endpoint. |
| Auth | `public`, `session`, `api_user`, `merchant`, `admin`, `webhook`, or `unknown`. |
| Permission | Specific permission or role gate. |
| CSRF | Whether write routes call `mg_require_csrf_for_write()`. |
| Scope check | Whether records are scoped to owner/merchant/admin authority. |
| Rate limit | Whether endpoint has explicit rate limiting. |
| Risk | `low`, `medium`, `high`, or `critical`. |
| Status | `reviewed`, `needs-review`, or `needs-fix`. |

---

## Initial inspected endpoints

| Endpoint | Method | Auth | Permission | CSRF | Scope check | Rate limit | Risk | Status | Notes |
|---|---|---|---|---|---|---|---|---|---|
| `/api/merchant/crm-campaign-send.php` | POST | merchant | `merchant.campaigns.manage` | yes | merchant-owned source contact and merchant-owned campaign | not explicit | medium | reviewed | Strong transactional flow with row locks, inventory checks, idempotency, wallet issuance, campaign events, CRM events, and PPPM bridge. Consider adding explicit rate limit. |
| `/api/merchant/customer-refund-send.php` | POST | merchant | `merchant.campaigns.manage` | yes | merchant-owned contact and `customer_refund` campaign | not explicit | medium | reviewed | Strong flow. Some overlap with generic CRM campaign send suggests future consolidation. Consider explicit rate limit and shared send service. |
| `/api/account/action-center-send.php` | POST | api_user | authenticated user | yes | validates Action Center item owner and PPPM owner before regift | not explicit | medium | reviewed | Strong PPPM ownership transfer path. Consider explicit send/regift rate limit. |
| `/api/stories/upload.php` | POST | api_user | authenticated user | yes | asset owner is current user | yes | medium | reviewed | Stronger upload endpoint. Uses MIME checks, image dimensions, optional ffprobe duration check, persistent storage, checksum, and catalog asset registration. |
| `/api/stories/create.php` | POST | api_user | authenticated user | yes | asset owned by user; merchant-linked product/campaign scoped to merchant | yes | medium | reviewed | Strong story creation route. Good 24-hour expiry and linked product/campaign checks. |
| `/api/public/media.php` | GET/HEAD | mixed public/private | asset visibility logic | n/a | owner/public reference checks | no | medium | reviewed | Controlled media delivery with storage resolution, cache headers, range support, and public visibility checks. Review public-reference rules periodically. |
| `/api/catalog/upload.php` | POST | permissioned | `catalog.assets.manage` | yes | asset owner is current user | not explicit | high | needs-fix | Uses MIME and size checks, but stores in `storage/private` with `private_local` instead of canonical persistent media storage. Should migrate to unified media service. |
| `/api/catalog/asset-file.php` | GET | mixed owner/public published | current user or published product reference | n/a | owner or published product reference | no | medium | needs-review | Serves `private_local` assets. Review cache behavior and unify with `/api/public/media.php` once catalog upload is migrated. |
| `/api/payments/checkout-session.php` | POST | permissioned | `commerce.checkout.create` | yes | published product/version lookup; one merchant per checkout | not explicit | medium | needs-review | Good idempotency and transaction use. Needs readability cleanup and success/cancel URL validation. |
| `/api/payments/sandbox-confirm.php` | POST | permissioned | `commerce.checkout.create` | yes | buyer-owned checkout session | not explicit | medium | reviewed | Properly blocks when live/non-sandbox. Confirms buyer-owned session and amount/currency match. |
| `/api/admin/system-sql-diagnostics.php` | GET | admin | `admin.health.view` through system health user helper | n/a | admin permission | yes | low | reviewed | Uses admin permission and rate limit. Documentation/ops surface only. |

---

## Endpoint families to inventory next

### Auth and account

- `/api/auth/*.php`
- `/api/account/*.php`
- `/api/me/*.php`

Checks:

- login/register/recovery rate limits
- session regeneration
- password reset token lifetime
- email verification token handling
- logout/session revocation

### Merchant

- `/api/merchant/*.php`
- merchant CRM endpoints
- merchant campaign endpoints
- merchant product/reward endpoints
- merchant PWA endpoints

Checks:

- `merchant_user_id` scoping
- workspace/team scoping
- package entitlement checks
- CSRF on writes
- explicit rate limits on expensive writes

### Campaigns/rewards/wallet

- `/api/public/campaigns/*.php`
- `/api/rewards/*.php`
- `/api/gifts/*.php`
- `/api/microgifts/*.php`
- `/api/pppm/*.php`

Checks:

- claim-code handling
- inventory locks
- issued-count reconciliation
- duplicate claim/send handling
- terminal status enforcement
- wallet/PPPM owner consistency

### Commerce/payments/ledger

- `/api/payments/*.php`
- `/api/finance/*.php`
- refund endpoints
- payout endpoints

Checks:

- provider signature verification
- live/sandbox mode gates
- amount/currency equality checks
- ledger balance checks
- idempotency replay behavior
- order fulfillment after payment only

### Uploads/media/social

- `/api/catalog/upload.php`
- `/api/social/media-upload.php`
- `/api/profiles/media.php`
- `/api/ads/upload-creative.php`
- `/api/stories/upload.php`
- blog media upload handlers

Checks:

- MIME verification with `finfo`
- extension normalization
- image dimension checks
- video duration checks
- persistent storage
- owner metadata
- no direct public executable uploads

### Blog/public content

- public blog pages
- admin blog/content studio pages
- blog save/publish actions
- media uploads
- RSS/sitemap endpoints

Checks:

- admin permission gates
- CSRF on writes
- HTML sanitizer policy
- URL validation
- featured image source policy
- public rendering escaping

### Admin/ops

- `/api/admin/*.php`
- `/admin/*.php`

Checks:

- specific admin permission per page/API
- rate limits on diagnostics/actions
- sensitive action confirmation tokens
- no secret exposure
- no SQL repair execution without explicit operator action

---

## Required matrix completion format

When reviewing each endpoint, add a row using this format:

```markdown
| `/api/example.php` | POST | merchant | `merchant.example.manage` | yes | merchant-owned row by `merchant_user_id` | yes/no | low/medium/high | reviewed/needs-fix | Notes. |
```

---

## Initial fix candidates from matrix

1. Add explicit rate limits to merchant send/regift endpoints.
2. Migrate catalog uploads to canonical persistent media storage.
3. Move blog featured-image uploads out of direct `/uploads` path.
4. Replace blog HTML sanitizer with stronger sanitizer or structured content rendering.
5. Validate checkout `success_url` and `cancel_url` as safe local paths or approved domains.
6. Consolidate duplicate customer-refund send logic with generic CRM campaign send service.
7. Add endpoint inventory automation or a static script to detect method/auth/CSRF helpers.
