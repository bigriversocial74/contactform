# Developer API database install

Run one SQL file for the Developer/Public API database layer:

```text
database/developer_api_single_install.sql
```

## What it creates

- `merchant_developer_apps`
- `merchant_api_keys`
- `developer_app_user_links`
- `distribution_api_request_logs`
- `developer_app_link_requests`
- `developer_webhook_events`
- `developer_webhook_attempts`
- `public_api_quota_buckets`
- `public_api_sandbox_rewards`
- Developer API permissions
- Developer webhook test permission
- `schema_migrations` records for the five Developer/Public API migration keys

## Operator workflow

1. Upload/deploy the current code from GitHub.
2. Open the database tool.
3. Run `database/developer_api_single_install.sql` once.
4. Refresh System Health.
5. Open the merchant Developer API workspace.

The file is idempotent. It uses `CREATE TABLE IF NOT EXISTS` and `INSERT IGNORE` where possible, so rerunning it should not recreate existing tables.

## Prerequisites

This file assumes the base Microgifter schema already exists, including:

- `users`
- `roles`
- `permissions`
- `role_permissions`
- `distribution_programs`
- `distribution_source_connections`
- `distribution_source_events`

If any of those base tables are missing, run the normal baseline migrations first.
