# Unified POS Customer & Purchase Sync v1

## New-chat / agent handoff

Repository: `bigriversocial74/contactform`

Integration/deploy branch: `integration-from-repair-20260628`

Docs-only planning branch: `planning/unified-pos-sync-v1-20260724`

Feature name: **Unified POS Customer & Purchase Sync**

Initial provider: **Square**

Future providers: **Shopify POS, Clover, Toast, Lightspeed**

## Critical workflow

Before implementation begins:

1. Verify the newest live head of `integration-from-repair-20260628`.
2. Read every document in this folder.
3. Inspect current canonical files before changing code, especially:
   - `api/integrations/_webhook_intake.php`
   - `includes/merchant-crm.php`
   - `includes/merchant-crm-identity.php`
   - `database/stage_12_merchant_crm.sql`
   - existing integration, webhook, OAuth, encryption, worker, and reconciliation patterns
4. Start each implementation phase from the newest integration head.
5. Keep each phase scoped and open its own PR back into `integration-from-repair-20260628`.
6. Do not merge without David Evans's explicit request.
7. Do not claim workflow success unless checks were actually run or verified.
8. Clearly report SQL, environment variables, cron, OAuth configuration, webhook configuration, deployment, and production verification separately.

## Current status

- Product decisions: complete
- Technical blueprint: complete
- Database blueprint: complete
- API contracts: complete
- Provider mapping: complete
- Scoped phase plan: complete
- QA and reconciliation plan: complete
- Deployment runbook: complete
- Implementation branch: none
- Implementation PR: none
- SQL file: not created
- SQL import: not performed
- OAuth credentials: not configured
- Webhooks: not configured
- One-minute cron: production support confirmed, but not installed for this feature
- Deployment: not started
- Production verification: not started

## Locked defaults

- Sync direction: POS → Microgifter only
- Initial import: customer directory only
- Historical purchase backfill: deferred optional tool
- Imported customers: Merchant CRM contacts, not Microgifter login accounts
- Matching order: external mapping → exact email → exact phone → manual review
- Name-only auto-matching: prohibited
- Anonymous purchases: merchant aggregate analytics only until identity is later resolved
- Line items: stored whenever available
- LTV: net merchandise value after discounts/refunds; tax, tips, and service charges excluded by default
- Gross paid, taxes, tips, service charges, and refunds: stored separately
- Gift-card sales: excluded from merchandise LTV to avoid double counting
- Custom attributes: merchant-selected allowlist
- Raw/redacted webhook payload retention: 90 days
- Webhook processing: verify and durably enqueue quickly; process asynchronously
- Worker cadence: every minute
- Disconnect behavior: revoke/delete credentials, stop sync, preserve normalized history
- Rollout: selected merchants first
- Reward automation: canonical Microgifter event bridge only; provider adapters never issue rewards directly
- Sensitive payment/card/device tokens: never stored

## Core architecture

```text
POS provider
→ provider-specific signature verification
→ durable webhook receipt and delivery-id deduplication
→ database-backed worker queue
→ provider adapter
→ normalized Microgifter customer/transaction schema
→ canonical POS ledger
→ CRM identity resolver and purchase projector
→ analytics / campaign / reward event bridge
```

## Core invariants

1. Provider-specific payloads never directly update CRM totals or issue rewards.
2. The normalized transaction ledger is the source of truth.
3. Webhook delivery idempotency, transaction revision idempotency, and business-effect idempotency are separate controls.
4. A completed purchase is applied once; later identity enrichment or refund effects may still apply independently.
5. Anonymous purchases never collapse into a shared fake CRM customer.
6. Imported POS customers never automatically become Microgifter user accounts.
7. Marketing consent is never inferred from a purchase or directory import.
8. Provider keys use extensible strings, not provider ENUM columns.
9. MySQL `JSON` is used, not PostgreSQL `JSONB`.
10. Tokens and webhook secrets are encrypted at rest and removed when disconnected.

## Documentation set

- `technical-blueprint.md` — architecture, invariants, processing flow, identity, LTV, security, UI, provider behavior, and non-goals
- `database-schema.md` — proposed tables, columns, indexes, states, retention, and source-of-truth rules
- `api-contracts.md` — merchant, OAuth, webhook, worker, reconciliation, matching, and normalized event contracts
- `provider-adapter-matrix.md` — Square v1 and future Shopify, Clover, Toast, and Lightspeed mappings
- `phase-plan.md` — ten scoped implementation phases, branch names, dependencies, acceptance criteria, and SQL expectations
- `qa-reconciliation.md` — canonical fixture, test matrix, concurrency/idempotency cases, security checks, and repair tooling
- `deployment-runbook.md` — environment, SQL, OAuth, webhook, cron, rollout, smoke tests, rollback, and status rules

## First instruction for the implementation agent

> Take over Unified POS Customer & Purchase Sync v1 for `bigriversocial74/contactform`. Read every file in `docs/unified-pos-sync-v1/` from branch `planning/unified-pos-sync-v1-20260724`. Verify the newest `integration-from-repair-20260628` head and inspect the current webhook, Merchant CRM, identity-resolution, OAuth, encryption, worker, and reconciliation architecture before changing code. Implement Phase 1 from `phase-plan.md` as a fresh scoped branch and PR. Do not merge without explicit approval. Clearly report SQL, environment variables, cron, OAuth/webhook setup, deployment, and production verification status.