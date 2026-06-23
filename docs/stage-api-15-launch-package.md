# Stage API-15 — Public API launch package

Stage API-15 packages the Public Distribution API for an outside developer and a merchant preparing for go-live.

## What this package covers

- Developer setup sequence from sandbox to live.
- Copy/paste API examples.
- A standalone test app that validates the docs.
- A fuller Local Quest Rewards demo ecosystem.
- Third-party reward wallet and claim reporting.
- Webhook configuration, test delivery, and signature verification.
- Error code reference.
- Launch checklist for merchants.
- Sandbox-to-live migration guide.
- Demo app ideas for partner-facing proof.

## Merchant launch sequence

1. Create or confirm a Distribution Program with attached products.
2. Create a test developer app.
3. Create a test API credential and store it server-side.
4. Use sandbox linked-account and reward issue endpoints.
5. Configure a webhook URL from the Developer API workspace.
6. Rotate and copy the webhook signing value into the developer backend.
7. Send a `webhook.test` delivery and confirm the receiver verifies it.
8. Use Live launch QA to clear blockers.
9. Clone the test app into a draft live app.
10. Promote the live app.
11. Create a live credential and store it server-side.

## Developer integration sequence

1. Store the API credential in backend configuration.
2. Call programs list to confirm the expected Distribution Program.
3. Start linked-account flow for an external user ID.
4. Store the returned linked account ID after approval.
5. Issue a reward with an idempotency key.
6. Poll status or consume webhook callbacks.
7. List rewards for the linked account when rendering an app wallet.
8. Report third-party claim actions back to Microgifter.
9. Verify webhook signatures before trusting payload data.

## Test app validation sequence

The docs are not finished until `examples/microgifter-api-test-app/` can be configured and used by following the public documentation only.

1. Copy `config.example.php` to `config.php`.
2. Add a test credential, program ID, template ID, and webhook signing value.
3. Run the PHP test app locally.
4. List programs.
5. Create a sandbox linked account.
6. Issue a sandbox reward.
7. Check reward status.
8. Receive and verify at least one webhook delivery.
9. Patch the docs for every unclear field, response, status, or error exposed by the test app.

## Full demo ecosystem

`examples/local-quest-rewards/` is the first fuller app ecosystem. It adds account login, account linking, quest state, quest-to-reward mapping, local permission checks, reward issue, reward status, reward wallet, claim reporting, webhook logging, and event history.

Use this app to drive the Microgift permission-system pass.

## Required public endpoints

- `GET /api/public/v1/programs/index.php`
- `POST /api/public/v1/account-links/start.php`
- `POST /api/public/v1/account-link-start.php` compatibility route
- `POST /api/public/v1/sandbox/linked-account.php`
- `POST /api/public/v1/rewards/issue.php`
- `GET /api/public/v1/rewards/status.php?id=<reward_id>`
- `GET /api/public/v1/rewards/list.php?linked_account_id=<linked_account_id>`
- `POST /api/public/v1/rewards/claim.php`

## Required merchant endpoints

- `GET /api/merchant/developer-api.php`
- `POST /api/merchant/developer-api.php`
- `POST /api/merchant/developer-api-credentials.php`
- `GET /api/merchant/developer-webhooks.php`
- `POST /api/merchant/developer-webhooks.php`
- `POST /api/merchant/developer-webhook-test.php` compatibility/test endpoint
- `GET /api/merchant/developer-api-launch-qa.php`
- `POST /api/merchant/developer-api-go-live.php`

## Related docs

- `developer-docs.php`
- `docs/stage-api-5-developer-webhooks.md`
- `docs/stage-api-6-public-docs-examples.md`
- `docs/public-api-test-app-build-plan.md`
- `docs/public-api-launch-checklist.md`
- `docs/public-api-error-reference.md`
- `docs/public-api-webhook-verification-examples.md`
- `docs/public-api-sandbox-live-guide.md`
- `docs/public-api-app-ideas.md`
- `docs/public-api-third-party-wallet-claim.md`
- `docs/microgift-permission-system-plan.md`
- `examples/microgifter-api-test-app/README.md`
- `examples/local-quest-rewards/README.md`
