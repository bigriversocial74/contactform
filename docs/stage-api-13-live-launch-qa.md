# Stage API-13 — Live launch QA

Stage API-13 adds a merchant-facing preflight check for public distribution API launch readiness.

## Endpoint

`GET /api/merchant/developer-api-launch-qa.php`

The endpoint returns:

- summary readiness counts
- per-app readiness state
- blocker and warning counts
- ordered checks for each developer app

## Checks

The launch QA checks:

- live-mode app
- active app
- default program attached
- active default program
- active source connection
- active live credential
- webhook URL configured
- webhook URL policy passes
- webhook signing key configured
- required API scopes are present
- recent successful request exists

Warnings do not block launch. Blockers must be cleared before a live app is considered ready.

## UI

The Developer API workspace now includes a Live launch QA panel. The panel shows readiness totals and each app's blockers and warnings.

## Runtime impact

No new database tables are required. This is a read-only QA surface over existing developer app, credential, program, webhook, and request log data.
