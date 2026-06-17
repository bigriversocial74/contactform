# Foundation Build Watchlist

## Purpose

This watchlist tracks major foundation areas that must stay aligned as Microgifter grows from HostGator-compatible private beta to AWS-ready production architecture.

## 1. Hosting/runtime profiles

Maintain two compatible paths:

- HostGator/cPanel private beta
- AWS production scale

Every future feature should declare its runtime mode and fallback behavior.

## 2. Public web root

Before public launch, move to a true public web root or equivalent front-controller deployment. Internal folders must never be browser-addressable.

## 3. Database migrations

Keep SQL migrations ordered and repeatable. The migration runner exists, but HostGator may require manual phpMyAdmin imports.

## 4. Tenant/account scope

Every future business table needs a clear owner/scope key:

- `account_id`
- `store_id`
- `owner_user_id`

## 5. Object-level authorization

Every endpoint that loads a record by ID must check ownership or scoped permission before returning data.

## 6. Async/outbox-first workflows

Slow actions should write to `outbox_events` first and process later:

- email
- SMS
- QR generation
- agent/LLM runs
- notification fanout
- analytics
- webhook delivery

## 7. Idempotency

Duplicate-sensitive writes need idempotency:

- checkout
- gift send
- gift claim
- voucher redemption
- payment webhook
- agent action

## 8. Realtime delivery

Do not require websockets on HostGator. Start with polling and use websockets only as an AWS-era enhancement.

## 9. Security logging and monitoring

Continue writing structured security logs. Before public traffic, connect logs to a monitoring/alerting process.

## 10. Test coverage

Every new module needs security tests for:

- guest denied
- user allowed only for own records
- unrelated user denied
- permissioned admin allowed
- CSRF failures
- rate limits
- idempotency behavior where relevant

## 11. Media/storage

HostGator can handle small early assets, but production media should move to S3 plus CDN.

## 12. Search and analytics

Do not overload transactional MySQL for large analytics/search. Start simple, then split analytics/search once product usage proves the need.

## Stage carry-forward

This watchlist should be reviewed at the end of every stage audit before moving forward.
