# Stage API-9 — Developer dashboard analytics and request visibility

Stage API-9 adds operating analytics to the merchant Developer API workspace.

## Data source

The dashboard analytics are returned from `GET /api/merchant/developer-api.php` in an `analytics` object alongside the existing apps, programs, keys, logs, and summary payload.

## Dashboard sections

The Developer API workspace now shows:

- total API requests
- requests in the last 24 hours
- errors in the last 24 hours
- rate-limit hits in the last 24 hours
- sandbox reward totals
- daily request trend for the last 14 days
- seven-day usage by developer app
- seven-day usage by access key
- active quota windows
- seven-day webhook event status

## Tables used

- `distribution_api_request_logs`
- `merchant_developer_apps`
- `merchant_api_keys`
- `public_api_quota_buckets`
- `developer_webhook_events`
- `public_api_sandbox_rewards`

## UI integration

The view renders analytics into these sections:

- `data-dev-api-analytics-kpis`
- `data-dev-api-daily`
- `data-dev-api-app-usage`
- `data-dev-api-key-usage`
- `data-dev-api-quota-buckets`
- `data-dev-api-webhook-analytics`

The browser renderer is `assets/js/merchant-developer-api-analytics.js`.

This stage does not add new database tables. It surfaces analytics from the request logs, quota buckets, webhook events, and sandbox reward tables added in earlier stages.
