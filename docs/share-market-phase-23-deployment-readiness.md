# Buy-In Phase 23 Deployment Readiness Checklist

Phase 23 adds a read-only deployment readiness checklist for the Buy-In admin stack.

## Scope

This phase adds:

- deployment readiness service
- read-only JSON readiness API
- admin readiness page
- table existence checks
- permission checks
- route/page/asset file checks
- missing install item list
- readiness score
- JSON download

## SQL

No new SQL is added in Phase 23.

The checklist verifies existing installed schema and files only.

## API

`GET /api/admin/share-market/deployment-readiness.php`

The API requires Share Market Admin permission and returns:

- readiness score
- ready status
- all checks
- missing checks
- summary counts

## Page

`/account-share-market-deployment-readiness.php`

The page shows:

- deployment ready status
- score
- total checks
- missing checks
- table/permission/file categories
- full readiness JSON

## Safety posture

This phase is read-only. It does not add SQL, does not change value state, and does not process any market action.

## Future use

Phase 24 can add a support/operator runbook checklist that tells an admin which SQL bundles and routes to verify after deployment.
