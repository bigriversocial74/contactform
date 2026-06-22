# Stage API-4.5 — Public API stabilization runbook

This pass stabilizes the public Distribution API chain before webhook delivery work begins.

## Route inventory

Canonical public API routes:

- `GET /api/public/v1/programs/index.php`
- `POST /api/public/v1/account-link-start.php`
- `POST /api/public/v1/account-links/start.php` — alias to the canonical account-link start route
- `POST /api/public/v1/account-link-complete.php`
- `POST /api/public/v1/rewards/issue.php`
- `GET /api/public/v1/rewards/status.php?id=reward-id`

Worker routes:

- `GET /api/distribution/issuance-worker.php`
- `POST /api/distribution/issuance-worker.php`
- `php scripts/run_distribution_issuance_worker.php --limit=25`

Validation helper:

- `php scripts/validate_public_distribution_api_routes.php`

## Migration order

Run the existing migrations in this order for the public API path:

1. `database/stage_3_pppm_core.sql`
2. `database/stage_3_pppm_activity_layer.sql`
3. `database/stage_3_pppm_delivery_assignment.sql`
4. `database/stage_4_product_asset_foundation.sql`
5. `database/stage_4e_distribution_external_inputs.sql`
6. `database/stage_public_distribution_api_foundation.sql`
7. `database/stage_public_distribution_api_account_links_note.sql`

## End-to-end manual check

1. Merchant creates a Distribution Program.
2. Merchant attaches at least one published product/template.
3. Merchant creates a Developer App.
4. Merchant creates a credential.
5. Developer calls account-link start.
6. User approves `/account-link.php?code=...`.
7. Developer receives `linked_account_id` on return URL.
8. Developer calls reward issue with `linked_account_id`.
9. Worker runs and creates a delivered PPPM item.
10. Developer calls reward status and sees reward lifecycle counts.
11. Recipient sees the item in the Microgifter INBOX.

## Cleanup decisions

- The nested `account-links/start.php` route is retained as an alias so external developers can use REST-style docs paths without hitting a placeholder.
- `account-link-start.php` remains the flat canonical implementation because the connector originally blocked the nested write path during Stage API-3.
- The route validator checks required files exist but does not make network calls or require live credentials.
- A future status enrichment should expose per-job PPPM item IDs once the connector allows the status endpoint patch.
