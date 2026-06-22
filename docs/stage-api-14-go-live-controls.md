# Stage API-14 — Go-live controls

Stage API-14 adds merchant-facing controls for safely moving public distribution API integrations from test mode to live mode.

## Endpoint

`POST /api/merchant/developer-api-go-live.php`

Actions:

- `clone_to_live` creates a draft live app from an existing test app without mutating the test app.
- `promote_live` promotes a live app to active after required go-live blockers are cleared.
- `create_live_credential` creates a reveal-once live credential after the live app passes core go-live requirements.

## Guardrails

Live promotion and live credential creation require:

- live app environment
- active default program
- usable source connection
- public HTTPS webhook URL
- webhook signing value configured
- required public API scopes

## UI

The Developer API Live launch QA panel now includes action buttons:

- Create live app
- Promote live
- Create live credential

Any returned webhook signing value or live credential is displayed in the existing copy-once credential reveal area.

## Runtime impact

No new tables are required. The stage uses existing developer app, source connection, credential, webhook, and metadata columns.
