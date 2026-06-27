# Store Canvas Schema Status Recovery

## Goal

Make Stage 20 Store Canvas readiness visible before a merchant attempts to use the live canvas.

## Built

- `/api/store/schema-status.php`
- Store Canvas status panel in `/merchant-canvas.php`
- Store Canvas JavaScript readiness gate in `assets/js/merchant-canvas.js`
- Store Canvas schema panel styling in `assets/css/merchant-canvas.css`
- Static validator: `scripts/validate_store_canvas_schema_status.php`

## What the endpoint checks

The status endpoint checks required Stage 20 tables, required columns, the migration row for `stage_20_agent_store_canvas`, and a safe merchant session read check when the schema is ready.

Required Stage 20 tables:

- `mg_agents`
- `mg_store_sessions`
- `mg_store_session_events`
- `mg_customer_store_history`
- `mg_agent_messages`

## Expected UI behavior

If Stage 20 is not installed, the Merchant Store Canvas page shows a setup panel with the missing tables or columns and points the operator to `database/stage_20_agent_store_canvas.sql`.

If Stage 20 is installed, the panel reports readiness and the canvas loads active customer sessions.

## Why this matters

The Store Canvas UI can now distinguish between a real empty canvas and an unavailable schema. This avoids debugging the customer-enter-store flow when the actual issue is that Stage 20 tables have not been imported.
