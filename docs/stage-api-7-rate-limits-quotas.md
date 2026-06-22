# Stage API-7 — Rate limits, request quotas, and abuse controls

Stage API-7 adds server-side quota enforcement to the Public Distribution API.

## Enforcement point

All public API routes call `mg_public_context()` from `api/public/v1/_public.php`. Stage API-7 enforces quotas after credential validation and scope checks, before the endpoint executes its business logic.

## Default limits

| Window | Environment variable | Default |
| --- | --- | --- |
| Per minute | `MG_PUBLIC_API_RATE_PER_MINUTE` | `60` requests |
| Per day | `MG_PUBLIC_API_DAILY_QUOTA` | `5000` requests |
| Per month | `MG_PUBLIC_API_MONTHLY_QUOTA` | `100000` requests |

The limits are checked per API key. Each request increments the minute, day, and month buckets atomically in MySQL.

## Data model

Migration: `database/stage_public_distribution_api_quotas.sql`

Table: `public_api_quota_buckets`

The table stores one row per API key, window scope, and window key:

- `bucket_scope`: `minute`, `day`, or `month`
- `bucket_key`: compact UTC window key, such as `202606220105`, `20260622`, or `202606`
- `limit_value`: the limit applied when the request was counted
- `used_count`: requests counted in that window
- `window_start` / `window_end`: UTC boundaries

## Response headers

Successful public API requests include minute-window headers:

```http
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 59
X-RateLimit-Reset: 1782092760
```

When a quota is exhausted, the API returns HTTP `429` and includes `Retry-After` plus the same rate-limit headers.

```json
{
  "ok": false,
  "error": "Public API request quota exceeded."
}
```

## Request logging

Quota failures are logged to `distribution_api_request_logs` with:

- `status_code`: `429`
- `response_status`: `rate_limited`
- `error_message`: `Public API request quota exceeded.`

If the quota check itself fails, the request is logged with `quota_error` and returns `500`, because missing quota storage means the abuse-control layer is not healthy.

## Notes

- Quotas currently apply at API-key level.
- Developer app/key level configuration UI can be added in a later stage.
- Status checks still happen after the credential is authenticated and scoped; unauthenticated requests are rejected before quota counting.
