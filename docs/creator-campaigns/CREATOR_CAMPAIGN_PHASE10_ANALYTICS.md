# Creator Campaign Phase 10 — Analytics and Performance

Phase 10 adds a read-only cross-phase reporting layer over the authoritative Creator Campaign records delivered in Phases 3–9.

## Delivered

- Merchant Creator Campaign analytics workspace at `/merchant-creator-analytics.php`.
- Creator-owned performance workspace at `/creator-campaign-analytics.php`.
- Date-range, campaign, and participant filters.
- Campaign and Creator comparison tables.
- Accepted landing views, unique clicks, engagements, and canonical conversions.
- Lead, checkout, purchase, claim, and redemption conversion mix.
- Deliverable assignment, completion, revision, revision-round, and overdue reporting.
- Currency-safe net earnings, scheduled payouts, paid payouts, and payout exceptions.
- Merchant-only current budget limit, available, reserved, and committed balances.
- Active dispute counts.
- Channel/platform performance and day/week/month trend buckets.
- Hardened CSV exports for campaigns, Creators, channels, timeseries, and deliverables.

## Authoritative data reuse

Phase 10 does not persist duplicate metric counters. Reports read directly from:

- `creator_campaign_participants`
- `creator_campaign_participant_deliverables`
- `creator_campaign_submissions`
- `creator_campaign_tracking_sources`
- `creator_campaign_tracking_events`
- `creator_campaign_attributions`
- `creator_campaign_earning_events`
- `creator_campaign_budgets`
- `creator_campaign_budget_events`
- `creator_campaign_payouts`
- `creator_campaign_disputes`

Only tracking events with `status='accepted'` are counted. Canonical attribution decisions must have `status IN ('attributed','overridden')`, and their underlying conversion event must still be accepted. Suspect, duplicate, invalidated, unattributed, and invalidated-attribution records remain available in their operational workspaces but are excluded from performance totals.

## Privacy and financial integrity

- Raw IP addresses, user-agent strings, session identifiers, and visitor identifiers are not exposed.
- All financial values remain integer minor units and are grouped by ISO currency.
- Currency values are never combined across currencies.
- Creator responses never include merchant budget limits or budget ledger balances.
- Merchant queries are restricted to the active merchant workspace.
- Creator queries require the active Creator user model, approved Creator eligibility, and object ownership.
- Selecting one Creator preserves the selected campaign summary while participant-level metrics remain restricted to that Creator.
- Conversion and completion rates return zero when the denominator is zero.

## CSV safety

CSV exports are generated on demand and are not stored. Cells beginning with spreadsheet formula characters (`=`, `+`, `-`, `@`, tab, or carriage return) are prefixed with an apostrophe before export.

## Permissions reused

No new permissions are introduced:

- Merchant: `merchant.intelligence.view`
- Creator: `creator.campaign_messages.view_own`

The Creator permission is the Phase 9 user-model-compatible view permission mapped to the canonical customer role. The existing active Creator model, approved profile, and participant ownership checks remain mandatory, so the permission alone does not expose Creator Campaign data to ordinary customer accounts.

## SQL

**No SQL required.** Phase 10 creates no tables, cached counters, materialized views, or report queues.

## Boundaries

Phase 10 does not modify tracking facts, attribution decisions, deliverables, earnings, budgets, payouts, disputes, CRM contacts, notification delivery, payment-provider transfers, or MCP execution.
