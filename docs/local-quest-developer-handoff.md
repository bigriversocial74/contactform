# Local Quest Partner Developer Handoff

This guide is the handoff path for a partner developer or internal QA reviewer running the Local Quest Rewards starter app.

## Start here

```text
examples/local-quest-rewards/start.php
```

The launcher now guides the full path:

1. Runtime diagnostics
2. Installer / SQL setup
3. API configuration review
4. Participant sign-in
5. Microgifter account linking
6. QR/geolocation quest action
7. Reward issue
8. Wallet status and claim reporting
9. Signed webhook test payloads
10. Webhook status and reconciliation
11. Admin readiness review
12. Optional demo seed/reset tools

## Runtime diagnostics

```text
examples/local-quest-rewards/runtime-diagnostics.php
```

Use this before the demo. It checks:

- SQL storage driver
- database connection
- app state load
- app public URL
- Microgifter base URL
- bearer credential presence
- default Distribution Program
- default reward template
- webhook signing value
- webhook endpoint URL

The page masks sensitive values and only shows whether secrets are present.

## API examples

```text
examples/local-quest-rewards/api-examples.php
```

Copy-ready cURL examples are included for:

- program listing
- sandbox linked account
- production account-link start
- reward issue
- reward status
- wallet claim/report

All examples assume the API key remains server-side and is sent with `Authorization: Bearer`.

## Webhook tools

```text
examples/local-quest-rewards/webhook-tools.php
```

Use this page to generate sample payloads and signature headers for local webhook testing. It creates:

- sample JSON payload
- `X-Microgifter-Event`
- `X-Microgifter-Delivery`
- `X-Microgifter-Timestamp`
- `X-Microgifter-Signature-Version`
- `X-Microgifter-Signature`
- terminal cURL command

If `webhook_secret` is not configured, the page displays a placeholder signature and explains that a rotated signing value is needed.

## Webhook status

```text
examples/local-quest-rewards/webhook.php
```

A browser GET renders webhook status and recent deliveries. A signed POST receives Microgifter webhook deliveries and skips Local Quest form CSRF through the `LQR_SKIP_CSRF` entrypoint flag.

## Admin demo tools

```text
examples/local-quest-rewards/admin-demo-tools.php
```

Admin-only utility page. It can seed one deterministic partner demo record:

- participant: `partner-demo@example.test`
- linked account: `sandbox_linked_partner_demo`
- completed quest: `coffee_checkin`
- reward: `reward_partner_demo_1001`
- item: `item_partner_demo_1001`
- webhook evidence: `webhook.test`

The reset action only removes the deterministic seed user and requires typing:

```text
RESET LOCAL QUEST DEMO
```

## Admin readiness

```text
examples/local-quest-rewards/admin-developer-readiness.php
```

Use this page after running the demo. It summarizes:

- config readiness
- local participant count
- linked accounts
- completions
- rewards issued
- claims reported
- verified webhook evidence

## Clone guidance

When cloning Local Quest into another starter app, keep the foundation pieces intact:

- `app.php`
- `security.php`
- `storage-sql.php`
- `install.php`
- `admin-auth.php`
- `admin-roles.php`
- `wallet.php`
- `wallet-actions.php`
- `webhook.php`
- `webhook-reconcile.php`
- SQL schemas

Then change the app-specific layer:

- `quests.php`
- `quest-controls.php`
- `cover.php`
- `index.php`
- `start.php`
- page copy and visual design
- reward mapping and action model

## Minimum handoff pass

Before sending to a developer, confirm:

- Runtime diagnostics has no critical open items.
- API examples match the current Microgifter environment.
- A participant can link or sandbox-link successfully.
- A quest can be completed.
- A reward can be issued and status-checked.
- Wallet claim/report can be submitted.
- A signed webhook can be verified.
- Admin readiness shows the demo evidence.
