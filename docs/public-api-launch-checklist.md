# Public Distribution API launch checklist

Use this checklist before handing a Microgifter Public API integration to an outside developer or partner.

## Merchant setup

- Distribution Program exists.
- Program status is active.
- Program has at least one attached product/template.
- Developer app exists in test mode.
- Test credential exists and is stored server-side.
- Sandbox linked-account test has been run.
- Sandbox reward issue test has been run.
- Webhook URL is configured.
- Webhook signing value is rotated and copied into the developer backend.
- Webhook test delivery succeeds.
- Live launch QA has no blockers.

## Live setup

- Test app is cloned to a draft live app.
- Live app uses a public HTTPS webhook endpoint.
- Live app has the required scopes:
  - `distribution:programs.read`
  - `distribution:rewards.issue`
  - `distribution:rewards.status`
- Live app is promoted only after QA blockers are clear.
- Live credential is created and copied once into the developer backend.
- Query-string credentials are not used.

## Developer backend

- API key is stored in a secret store or environment variable.
- Requests use `Authorization: Bearer <credential>`.
- Reward issue requests use `X-Idempotency-Key`.
- Webhook handler verifies timestamp and signature.
- Webhook handler rejects old timestamps.
- Webhook handler can safely process duplicate deliveries.
- Logs include `X-Request-ID` and `X-Microgifter-Delivery` values for support.
