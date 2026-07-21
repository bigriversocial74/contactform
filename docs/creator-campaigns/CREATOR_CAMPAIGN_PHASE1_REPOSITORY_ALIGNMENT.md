# Creator Campaign Phase 1 Repository Alignment

## Build authority

This implementation follows the canonical Creator Campaign documents in this directory:

1. `CREATOR_CAMPAIGN_SYSTEM_SPECIFICATION.md`
2. `CREATOR_CAMPAIGN_DATABASE_API_OUTLINE.md`
3. `CREATOR_CAMPAIGN_UI_MOCKUPS.md`
4. `CREATOR_CAMPAIGN_UI_PROMPTS.md`
5. `CREATOR_CAMPAIGN_MCP_INTEGRATION_AMENDMENT.md`

The current handoff's narrow **Native Foundation** boundary controls this first production PR. The broader phase grouping in the MCP amendment does not authorize UI, applications, agreements, payouts, or MCP execution in this PR.

## Repository decisions

- `users` remains the only login identity.
- Creator and Merchant are active operating models, not new account types.
- Creator eligibility requires an active user, active Creator-model assignment, and active `creator_profiles` record.
- `marketing_affiliate` is explicitly excluded from Creator eligibility.
- `merchant_workspaces.id` is the canonical campaign owner.
- Merchant team members act inside the workspace; they never become campaign owners.
- `catalog_products` and `catalog_product_versions` are reused through workspace-owner validation.
- The existing CRM/reward `campaigns` table is not reused or modified.
- Creator Campaign lifecycle history is append-only and separate from global audit/event streams.
- Idempotency keys are stored as SHA-256 hashes, not raw external request tokens.
- All mutable campaign writes use optimistic locking through `lock_version`.

## Phase 1 delivered

### Schema and ownership

- `creator_campaigns`
- `creator_campaign_products`
- `creator_campaign_eligibility_rules`
- `creator_campaign_status_events`
- workspace, catalog, asset, and user foreign keys
- workspace-scoped internal-reference and creation-idempotency uniqueness

### Authorization and identity

- active Merchant-model guard
- Merchant active-context guard
- active workspace requirement
- cross-workspace denial
- platform-role plus workspace-role permission checks
- exact Creator-model eligibility projection

### Service foundation

- idempotent draft creation
- draft/scheduled optimistic-lock updates
- workspace-owned product attachment
- workspace-owned cover-asset validation
- eligibility-rule replacement
- approved lifecycle transitions
- append-only status events with before/after snapshots
- global audit and event emission

### Validation and delivery

- scored CLI validator
- PHPUnit source/behavior contract
- PHP 8.2 and 8.3 workflow matrix
- canonical MySQL 8 migration-chain validation
- migration-manifest validation

## Status lifecycle

`draft -> scheduled|active|cancelled`

`scheduled -> draft|active|paused|cancelled`

`active -> paused|completed|cancelled`

`paused -> active|completed|cancelled`

`completed -> archived`

`cancelled -> archived`

`archived -> terminal`

Every transition requires an idempotency key, reason, expected lock version, workspace authorization, and an append-only event.

## Out of scope

The following are intentionally deferred to later approved phases:

- Merchant Campaign Builder pages or HTTP APIs
- Creator directory UI and campaign matching
- applications, invitations, participants, and shortlists
- agreements, signatures, amendments, and acceptance logs
- deliverables, content review, and social-platform ingestion
- tracking links, conversions, CRM attribution, and reporting
- compensation budgets, earnings, payouts, refunds, or disputes
- messaging and notifications specific to Creator Campaign participation
- MCP read tools, draft tools, approval grants, or execution tools

## Section scoring gate

A section is complete only when its validator category scores 20/20 and the full foundation scores 100/100:

| Section | Required score |
|---|---:|
| Schema and ownership | 20/20 |
| Identity and authorization | 20/20 |
| Lifecycle and concurrency | 20/20 |
| Service boundaries | 20/20 |
| Validation and delivery | 20/20 |
| **Total** | **100/100** |
