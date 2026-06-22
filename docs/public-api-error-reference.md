# Public API error reference

Public Distribution API errors are returned as JSON through the shared Microgifter API envelope.

## Response shape

```json
{
  "ok": false,
  "message": "Public API credential scope is insufficient."
}
```

## Status codes

| Status | Meaning | Common fix |
|---|---|---|
| 400 | Malformed request body or unsupported request shape. | Send valid JSON and required headers. |
| 401 | Missing, invalid, expired, or revoked API credential. | Use `Authorization: Bearer <credential>` and confirm the credential is active. |
| 403 | Credential lacks the required scope, sandbox-only endpoint was called with a live key, or return URL origin is not allowed. | Confirm scopes, app environment, and allowed origins. |
| 404 | Program, linked account, product template, reward, or developer resource was not found. | Confirm public IDs and merchant app access. |
| 409 | Program inactive, capacity exceeded, product limit reached, recipient limit reached, or live launch blocker exists. | Resolve the conflict before retrying. |
| 422 | Payload is missing required fields or includes invalid values. | Validate the request body before sending. |
| 429 | Minute, daily, or monthly quota exceeded. | Read `Retry-After` and rate-limit headers before retrying. |
| 500 | Server-side processing failure. | Log `X-Request-ID`, stop retries if persistent, and contact support. |

## Rate-limit headers

- `X-RateLimit-Limit`
- `X-RateLimit-Remaining`
- `X-RateLimit-Reset`
- `Retry-After` on 429 responses

## Retry rules

- Retry `429` only after `Retry-After`.
- Retry `5xx` with exponential backoff.
- Do not retry `401`, `403`, or `422` without changing credentials or payload.
- Reward issue retries must reuse the same `X-Idempotency-Key` for the same external event.
