# Microgifter Codebase Audit v1

Date: 2026-07-05
Base branch: `integration-from-repair-20260628`
Verified base commit: `950da3e226b4c95cb49b4749e333c60d02bc1c4f`
Scope: General code review, security audit, data-storage review, merchant social gifting flow review, and maintainability/vibe-coding pass.

This document is an audit artifact only. It does not change runtime behavior.

---

## Executive score

**Overall score: 7.1 / 10**

Microgifter is no longer just prototype code. The active codebase has a real production foundation: session hardening, CSRF helpers, DB-backed session validation, admin permission gates, prepared SQL, audit/security logging, persistent media storage, idempotency patterns, ledger balancing, transactional wallet/PPPM flows, and operational health tooling.

The main issue is not absence of engineering foundation. The main issue is **inconsistency across generations of code**. Some newer modules are strong and production-minded, while older or recently patched modules still use mixed storage patterns, duplicate files, one-off assets, and lighter sanitization.

To reach 10/10, Microgifter needs a normalization pass: one upload/storage system, one endpoint security standard, one migration standard, one media-delivery policy, one route/source tree, and fewer patch-layer assets.

---

## Category scores

| Area | Score | Notes |
|---|---:|---|
| Core security | 8.0 | Strong helpers, DB-backed sessions, CSRF, security logs. Needs endpoint consistency verification. |
| API authorization | 7.4 | Newer endpoints are good. Older endpoints need a full matrix pass. |
| Data storage | 6.6 | Strong ledger/PPPM/event direction, but media storage and soft-FK drift need cleanup. |
| Merchant gifting flow | 8.1 | Campaign send, customer refund send, wallet issuance, and PPPM bridge are strong. |
| Payments/ledger | 7.8 | Balanced ledger and idempotency are strong. Checkout code needs readability and URL validation pass. |
| Media/uploads | 5.8 | Stories are strong; blog/catalog storage is inconsistent. |
| Blog/public content | 5.9 | Good feature foundation, but raw HTML and upload handling need hardening. |
| Admin/ops | 7.8 | Admin pages and diagnostics have permission gates and rate limits. |
| Maintainability/vibe code | 5.7 | Too many patch assets, duplicate trees, one-line CSS/PHP, and legacy paths. |
| Tests/deploy readiness | 6.8 | Migration manifest and diagnostics exist. Needs contract tests for critical flows. |

---

## Positive findings

### 1. Security foundation exists

The app has a centralized session and app bootstrap. Session cookies are configured with HttpOnly and SameSite=Lax, with Secure enabled on HTTPS. CSRF helpers exist and write endpoints can enforce CSRF through a centralized helper.

API auth is stronger than a basic session check. Protected APIs refresh the user from the database, validate DB-backed session state, and reject inactive accounts.

API security headers are centralized for direct API requests, including `nosniff`, frame denial, restrictive permissions policy, and no-store caching.

### 2. Merchant campaign/reward send flow is strong

The CRM campaign send flow is not a quick patch. It includes:

- `POST` requirement
- merchant campaign permission check
- merchant workspace requirement
- CSRF check
- transaction boundary
- row locks with `FOR UPDATE`
- merchant-owned contact lookup
- merchant-owned campaign lookup
- active campaign/reward checks
- start/end date checks
- campaign and reward inventory checks
- idempotency handling
- wallet item creation
- issued-count updates
- campaign event records
- CRM event records
- PPPM bridge support

The newer customer-refund send flow follows the same pattern and properly restricts the campaign type to `customer_refund`.

### 3. PPPM ownership direction is strong

The canonical PPPM owner transfer function locks the PPPM item, blocks terminal statuses, validates transfer authority, syncs entitlements, updates owner/recipient state, records PPPM events, and emits app events.

The Action Center regift flow checks both Action Center ownership and PPPM ownership before transferring.

### 4. Ledger/accounting foundation is stronger than average

The money engine enforces balanced ledger transactions before posting. It validates idempotent replays against stored entries, preventing a reused idempotency key from silently creating a different transaction.

Payment capture updates the order, payment intent, transactions, ledger, history, receipts, and fulfillment issuance inside the workflow.

---

## Critical or high-priority risks

### Risk 1: Mixed media storage patterns

The persistent media storage helper is production-minded: absolute storage root, outside-web-root enforcement, normalized storage keys, traversal protection, and controlled public delivery.

However, not every upload path uses it.

Examples:

- Story uploads use the persistent storage abstraction and are comparatively strong.
- Catalog uploads still store under `storage/private` using the `private_local` provider.
- Blog featured images save under `/uploads/blog/featured` and do not appear to use the persistent storage abstraction.

**Risk:** inconsistent file storage makes uploads harder to secure, audit, migrate, back up, and serve safely.

**10/10 target:** all user/admin-uploaded media should pass through one upload service and one media-asset registry.

### Risk 2: Blog HTML sanitization is not strong enough

The blog module stores and renders HTML. The current sanitizer uses `strip_tags` and regex cleanup. That is better than nothing, but it is not a full HTML sanitizer.

**Risk:** admin-only publishing reduces exposure, but if an admin account is compromised or if lower-permission publishing is added later, public XSS becomes possible.

**10/10 target:** replace regex sanitization with a robust HTML sanitizer policy or move to structured content blocks rendered by trusted templates.

### Risk 3: Shared-host schema compatibility creates data drift risk

Some migrations intentionally avoid foreign keys for compatibility with shared hosts and mixed-engine tables. That is understandable for the deploy environment, but it means referential integrity must be enforced by application code and diagnostics.

**Risk:** orphaned records and counter drift become likely over time.

**10/10 target:** keep shared-host compatibility but add integrity diagnostics and reconciliation jobs for soft foreign keys.

### Risk 4: Duplicate/legacy tree creates wrong-file risk

The repo still has root runtime files and duplicate `microgifter-main/...` runtime files. Some duplicates are similar but not identical.

**Risk:** future fixes may land in the wrong tree, or old behavior may be reintroduced by mistake.

**10/10 target:** quarantine, archive, or delete legacy duplicate runtime trees after confirming deploy references.

### Risk 5: Patch-layer CSS/JS hurts reviewability

Some CSS and JS files are large, compressed, or patch-like. This makes review harder and increases the chance of regressions.

**Risk:** professional UI polish becomes harder because multiple patches fight the base component structure.

**10/10 target:** consolidate module assets, format CSS/JS, and reduce one-off patch files.

---

## Data-storage review

### Good patterns

- Public IDs are widely used, which helps avoid exposing sequential DB IDs.
- Wallet/PPPM/event flows store snapshots and event histories.
- Ledger posting enforces debit/credit balance.
- Many mutation flows use transactions and `FOR UPDATE` locks.
- Idempotency exists in checkout, CRM sends, PPPM transfers, ledger posting, and regift flows.

### Obvious storage concerns

1. **Media has multiple storage providers.**
   - `persistent_local`
   - `private_local`
   - direct `/uploads/...`

2. **JSON metadata is used heavily.**
   This is acceptable for flexible context, but recurring query fields should graduate into explicit columns.

3. **Issued counters can drift.**
   Campaign and reward issued counts are incremented during flows. The app needs diagnostics that compare counters against wallet/campaign-event reality.

4. **Soft foreign keys need audits.**
   If the database cannot always enforce FK constraints, system health should regularly report orphan counts.

5. **Legacy duplicate tables and flows need retirement decisions.**
   Old PPPM/microgift/wallet functions should be mapped to canonical authorities or marked deprecated.

---

## Security review

### Strong existing controls

- Central API bootstrap
- Central JSON response helpers
- Central CSRF helpers
- DB-backed session validation
- Permission helpers
- Admin page auth helpers
- Rate limiter
- Security logging with redaction
- Prepared SQL in inspected high-risk flows
- Apache blocking for docs, database, tests, includes, scripts, `.env`, `.git`, backup/log/sql files

### Security work still needed

1. Create an endpoint security matrix for every `/api/**/*.php` file.
2. Confirm every write endpoint calls `mg_require_csrf_for_write()` unless intentionally public/webhook-only.
3. Confirm every merchant endpoint scopes records by `merchant_user_id` or workspace/team membership.
4. Confirm every admin endpoint uses a specific admin permission, not only generic auth.
5. Confirm every public endpoint has strict input validation and no accidental data leakage.
6. Confirm upload endpoints use MIME verification, file-size limits, dimensions/duration checks, and controlled storage.
7. Confirm URLs accepted from users are either relative or validated against an allowlist.
8. Confirm public rendering uses `mg_e()` or sanitized/trusted rendering.

---

## Merchant social gifting flow review

The current merchant social gifting flow is conceptually strong:

1. Merchant creates products/rewards/campaigns.
2. Merchant CRM can select a customer/contact.
3. Merchant sends a reward-backed campaign or customer refund/make-good voucher.
4. Wallet item is created.
5. Campaign and CRM events are recorded.
6. PPPM bridge can connect wallet issuance to post-purchase product management.
7. Customer can receive, claim, regift, redeem, and trigger follow-up events.

### Main flow risks

- Direct wallet issuance and PPPM issuance need one canonical ownership authority.
- Customer account requirement currently blocks some wallet sends; invite fallback must be consistently implemented.
- Duplicate prevention should be standardized across all campaign send variants.
- Inventory and issued-count reconciliation should become an admin diagnostic.
- Claim, regift, refund, and make-good flows should have contract tests.

---

## 10/10 roadmap

### PR 1: Endpoint/security matrix

Create a durable endpoint inventory for every API route.

For each endpoint:

- method
- auth requirement
- permission requirement
- CSRF requirement
- rate limit
- owner/merchant scope
- SQL style
- response format
- upload behavior
- risk level
- fix notes

### PR 2: Upload/media hardening

Unify uploads behind one service:

- MIME validation with `finfo`
- image dimension validation
- video duration validation where relevant
- max-size by type
- persistent storage outside web root
- catalog asset record for all media
- controlled serving endpoint
- checksum and owner metadata
- orphan cleanup diagnostics

### PR 3: Blog content hardening

- Replace regex sanitizer with a real sanitizer or structured content blocks.
- Validate canonical URLs.
- Validate featured image references.
- Move blog uploads into the canonical media system.
- Add public post rendering tests.

### PR 4: Data-integrity diagnostics

Add diagnostics for:

- orphaned wallet items
- orphaned campaign contacts
- orphaned catalog assets
- missing media files
- campaign issued-count drift
- reward issued-count drift
- PPPM owner/recipient mismatch
- entitlement transfer mismatch
- ledger imbalance
- paid order without issued items
- issued items for unpaid orders

### PR 5: Critical flow contract tests

Add tests for:

- campaign reward send
- customer refund send
- duplicate idempotency
- inventory exhausted
- expired campaign blocked
- wallet issuance
- PPPM bridge
- regift
- claim
- redemption
- refund/make-good

### PR 6: Legacy tree cleanup

- Inventory duplicate files under `microgifter-main/...`.
- Confirm active deployment references.
- Quarantine or delete inactive duplicate runtime files.
- Prevent future PRs from modifying stale paths unless explicitly intended.

### PR 7: UI/vibe-code cleanup

- Format one-line CSS and JS.
- Consolidate patch files into module-level assets.
- Remove dead CSS selectors.
- Normalize frontend component patterns.
- Reduce inline string-template HTML where practical.

---

## Suggested fix order

1. Endpoint/security matrix
2. Upload/media hardening
3. Blog sanitizer and blog media rewrite
4. Data-integrity diagnostics
5. Critical flow contract tests
6. Legacy duplicate tree cleanup
7. UI/vibe-code cleanup

---

## Current production-readiness judgment

Microgifter is feature-rich and has several production-grade foundations, especially around merchant campaign sending, PPPM ownership, session security, and ledger posting.

The code should not be treated as 10/10 production-clean yet because storage, content rendering, duplicate trees, endpoint consistency, and test coverage need hardening.

**Best next action:** complete the endpoint/security matrix, then start the upload/media hardening PR.
