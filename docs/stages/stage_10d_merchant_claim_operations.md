# Stage 10D — Merchant Claim APIs, Operational History, Rate Limits, and Escalation

## Purpose

Stage 10D exposes the Stage 10C atomic redemption transaction through protected merchant APIs and adds operational controls around abuse, history, escalation, and retryable downstream delivery.

## APIs

- `POST /api/merchant/microgift-claim.php`
- `GET /api/merchant/microgift-claim-history.php`
- `GET|POST /api/admin/microgift-claim-escalations.php`

Merchant claim writes require authentication, `merchant.location_claim.execute`, and CSRF validation. History reads require `merchant.location_claim.history`. Escalation management is restricted to `microgift.claim_escalations.manage`.

## Rate limits

Rate-limit buckets are enforced for actor, merchant, location, gift, and optional network fingerprint scopes. Buckets are selected with `FOR UPDATE`, reset at window boundaries, and may set a temporary `blocked_until` time.

A blocked request creates a normalized `rate_limited` attempt and an operational escalation without exposing submitted claim credentials.

## Operational history

Merchant history is always scoped by `merchant_user_id`. It joins attempts to canonical Microgift instances, merchant locations, and completed redemptions, and supports result, location, and bounded limit filters.

## Escalation

Escalations are created for:

- rate-limit violations,
- repeated invalid location codes,
- merchant mismatches,
- disallowed locations,
- internal failures.

Each escalation is linked to the existing `microgift_review_items` workflow so administrators can start, resolve, or dismiss it through one operational review surface.

## Retryable outbox

Successful claims enqueue a credential-free `merchant_claim.completed` operational message. The outbox supports Pending, Processing, Delivered, Failed, and Dead states for later workers.

## Scope boundary

A later stage may add outbox workers, merchant dashboards, alert delivery adapters, configurable rate policies, and retention/archival jobs. Stage 10D establishes the protected contracts and persistent operational state.
