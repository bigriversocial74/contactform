# Stage API-15 — Public API launch package

Stage API-15 packages the Public Distribution API for an outside developer and a merchant preparing for go-live.

## What this package covers

- Developer setup sequence from sandbox to live
- Copy/paste API examples
- Webhook signature verification
- Error code reference
- Launch checklist for merchants
- Sandbox-to-live migration guide

## Merchant launch sequence

1. Create or confirm a Distribution Program with attached products.
2. Create a test developer app.
3. Create a test API credential and store it server-side.
4. Use sandbox linked-account and reward issue endpoints.
5. Configure a webhook URL and rotate the signing value.
6. Confirm webhook verification in the developer backend.
7. Use Live launch QA to clear blockers.
8. Clone the test app into a draft live app.
9. Promote the live app.
10. Create a live credential and store it server-side.

## Developer integration sequence

1. Store the API credential in backend configuration.
2. Call programs list to confirm the expected Distribution Program.
3. Start linked-account flow for an external user ID.
4. Store the returned linked account ID after approval.
5. Issue a reward with an idempotency key.
6. Poll status or consume webhook callbacks.
7. Verify webhook signatures before trusting payload data.

## Required public endpoints

- `GET /api/public/v1/programs/index.php`
- `POST /api/public/v1/account-link-start.php`
- `POST /api/public/v1/sandbox/linked-account.php`
- `POST /api/public/v1/rewards/issue.php`
- `GET /api/public/v1/rewards/status.php?id=<reward_id>`

## Required merchant endpoints

- `GET /api/merchant/developer-api.php`
- `POST /api/merchant/developer-api.php`
- `POST /api/merchant/developer-api-credentials.php`
- `GET /api/merchant/developer-api-launch-qa.php`
- `POST /api/merchant/developer-api-go-live.php`
- `POST /api/merchant/developer-webhook-test.php`

## Related docs

- `docs/public-api-launch-checklist.md`
- `docs/public-api-error-reference.md`
- `docs/public-api-webhook-verification-examples.md`
- `docs/public-api-sandbox-to-live-guide.md`
