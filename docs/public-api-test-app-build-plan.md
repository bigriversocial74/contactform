# Public Distribution API test app build plan

The Microgifter Public Distribution API documentation is good enough when a small outside-developer app can be built from the docs without private Microgifter knowledge.

## Goal

Build a standalone app that behaves like a third-party integration. The app should simulate a game, loyalty tool, event app, campaign backend, or partner platform that rewards a user with a merchant-approved Microgift.

## Pass condition

A developer using only the public docs can:

1. Configure `MG_BASE_URL`, `MG_API_KEY`, `MG_PROGRAM_ID`, and `MG_TEMPLATE_ID`.
2. List available Distribution Programs.
3. Create a sandbox linked account for an external user ID.
4. Issue a sandbox reward with an idempotency key.
5. Poll reward status.
6. Receive webhook deliveries.
7. Verify webhook signatures before trusting payload data.
8. Understand and recover from common API errors.

If any of these steps requires guessing, the docs are not done.

## Reference app

The repo includes a framework-free PHP demo:

```text
examples/microgifter-api-test-app/
  README.md
  config.example.php
  index.php
  webhook.php
```

The app intentionally avoids a database, framework, build step, and frontend tooling. It is a docs validation harness, not a production SDK.

## Screen/actions

The main page should expose these actions:

| Action | Endpoint | Required scope |
|---|---|---|
| List programs | `GET /api/public/v1/programs/index.php` | `distribution:programs.read` |
| Create sandbox linked account | `POST /api/public/v1/sandbox/linked-account.php` | `distribution:rewards.issue` |
| Issue reward | `POST /api/public/v1/rewards/issue.php` | `distribution:rewards.issue` |
| Check reward status | `GET /api/public/v1/rewards/status.php?id=<reward_id>` | `distribution:rewards.status` |
| Receive webhook | developer app webhook URL | configured signing value |

## Required configuration

```php
return [
    'base_url' => 'https://microgifter.com',
    'api_key' => 'mg_test_replace_with_server_side_key',
    'program_id' => 'dist_prog_replace_me',
    'template_id' => 'tmpl_replace_me',
    'webhook_secret' => 'replace_with_rotated_webhook_signing_value',
];
```

## Documentation failures to look for

- Missing response examples.
- Endpoint paths that do not match the real routes.
- Missing required request fields.
- Missing status lifecycle definitions.
- Missing webhook payload examples.
- Missing webhook signature verification details.
- Missing rate-limit and retry guidance.
- Ambiguous sandbox versus production behavior.

## App build sequence

1. Copy `config.example.php` to `config.php`.
2. Add a test API credential from the merchant developer app screen.
3. Add a program ID and template ID from the programs response or merchant workspace.
4. Run the app locally with `php -S 127.0.0.1:8088 -t examples/microgifter-api-test-app`.
5. Use a tunnel for webhook testing if the merchant app requires a public HTTPS URL.
6. Click each action in order.
7. Compare request/response behavior to the docs.
8. Patch the docs immediately when the test app exposes a gap.

## Done means

The docs, examples, and test app agree on endpoint paths, request bodies, response shapes, status values, webhook headers, webhook payloads, and retry behavior.
