# Unified POS Customer & Purchase Sync v1 — Scoped Phase Plan

Each phase should begin from the latest merged `integration-from-repair-20260628` head and use a fresh scoped branch. Do not stack several unmerged phases on one long-lived branch.

## Phase 1 — Provider-neutral POS foundation

Expected branch:

```text
feature/pos-sync-provider-foundation-v1
```

### Scope

- Add the idempotent master SQL migration.
- Add POS feature/entitlement state.
- Add provider registry and adapter interface.
- Add secure connection and location models.
- Add normalized customer, transaction, item, effect, queue, rollup, sync-run, and outbox data services.
- Extend/reuse generic provider webhook receipt without breaking existing consumers.
- Add database-backed worker claiming, retry states, stale-lock recovery, and dead-letter states.
- Add canonical normalized customer/transaction validators.
- Add encryption abstraction for provider credentials using the repository's approved secret-management pattern.
- Add initial static/PHP/SQL validation workflow.

### Acceptance

- Schema installs twice without error.
- Provider adapter can be registered without changing core transaction/CRM logic.
- Queue claims are concurrency-safe.
- Duplicate provider event IDs cannot create duplicate jobs.
- Conflicting replay is quarantined.
- No sensitive payment-token columns or logs exist.
- Feature defaults to disabled or admin-only.

### SQL

Required:

```text
database/20260724_unified_pos_customer_purchase_sync_v1_single_install.sql
```

No SQL should be imported or deployment claimed until separately confirmed.

---

## Phase 2 — Square OAuth and connection management

Expected branch:

```text
feature/pos-sync-square-oauth-v1
```

Depends on: Phase 1 merged.

### Scope

- Square OAuth start/callback.
- Single-use state validation.
- Minimum read-only scope request.
- Token exchange, encryption, expiry, and refresh handling.
- Square merchant/account discovery.
- Square location discovery and enable/disable controls.
- Merchant Integrations Square card and setup flow.
- Connection health, scope validation, reconnect, and disconnect.
- Sandbox/production separation.

### Acceptance

- Merchant can connect a Square Sandbox account.
- Granted scopes are verified and displayed safely.
- Credentials are encrypted and never returned by APIs.
- Invalid/replayed OAuth state fails.
- Merchant cannot access another merchant's connection.
- Disconnect revokes/clears credentials and preserves history.

### SQL

No additional SQL expected.

---

## Phase 3 — Square customer-directory synchronization

Expected branch:

```text
feature/pos-sync-square-customers-v1
```

Depends on: Phases 1–2 merged.

### Scope

- Paginated initial customer import.
- Sync-run progress and resumable cursor.
- Square customer created/updated/deleted webhook normalization.
- Merchant-selected visible custom-attribute allowlist.
- Custom-attribute value/definition webhook handling.
- External customer upsert.
- CRM identity matching through external mapping, exact email, exact phone, or new CRM contact.
- Ambiguous match review queue.
- Consent/unsubscribe preservation.
- Customer list and match-review merchant UI.

### Acceptance

- Initial import processes all pages without duplicate mappings.
- Imported customers become CRM contacts, not Microgifter login accounts.
- Name-only matching never occurs automatically.
- Email/phone conflict becomes ambiguous.
- Customer deletion preserves transaction identity and marks mapping deleted.
- Custom attributes respect merchant allowlist.
- Replayed customer events are idempotent.

### SQL

No additional SQL expected.

---

## Phase 4 — Square purchase and refund ingestion

Expected branch:

```text
feature/pos-sync-square-purchases-v1
```

Depends on: Phases 1–3 merged.

### Scope

- Square provider-specific webhook endpoint and signature validation.
- `payment.updated` intake and normalization.
- Completed-status business effect.
- Square order retrieval for line items.
- Anonymous purchase handling.
- Late customer identity enrichment.
- Out-of-order update protection.
- Transaction revision hash/version logic.
- `refund.updated` normalization.
- Partial/full completed refund effects.
- Gift-card line detection/exclusion from merchandise LTV where reliably identifiable.
- Disabled-location policy.
- Square fixtures and focused workflow.

### Acceptance

- One completed payment produces one completion effect despite duplicate/repeated events.
- Later payment update may enrich identity without increasing totals twice.
- Order line items reconcile to provider totals or are flagged incomplete.
- Anonymous purchase does not create a fake CRM contact.
- Completed refund applies once.
- Pending/failed refund does not alter LTV.
- Out-of-order events converge on the newest canonical provider state.

### SQL

No additional SQL expected.

---

## Phase 5 — Merchant CRM purchase projection and LTV

Expected branch:

```text
feature/pos-sync-crm-ltv-v1
```

Depends on: Phase 4 merged.

### Scope

- Project identified completed purchases to canonical CRM events.
- Project completed refunds.
- Maintain `merchant_crm_pos_rollups`.
- Update general Merchant CRM purchase stage/last-purchase fields through canonical services.
- Customer-profile POS summary and itemized timeline.
- Provider/location filters.
- Gross paid, net paid, merchandise LTV, refund total, purchase count, average order value.
- Recompute-safe rollup service.
- Identity enrichment projection for formerly anonymous transactions.

### Acceptance

- Completion updates CRM once.
- Refund adjusts rollups deterministically.
- Merchandise LTV excludes tax, tip, service charge, and gift-card sales by default.
- Anonymous purchases appear only in merchant aggregate analytics.
- Linking an anonymous transaction later updates the correct CRM contact without duplicate completion.
- Rollup recomputation equals incremental results.

### SQL

No additional SQL expected unless live CRM architecture requires a documented correction.

---

## Phase 6 — Merchant operations, worker health, and reconciliation

Expected branch:

```text
feature/pos-sync-merchant-operations-v1
```

Depends on: Phases 1–5 merged.

### Scope

- Complete `/merchant-integrations.php` POS management surface.
- Connection, location, import, queue, transaction, and error dashboards.
- Failed/retryable/dead-letter job review.
- Authorized retry controls.
- Reconciliation API and CLI.
- Stale lock repair.
- Customer mapping health.
- Transaction drill-down.
- 90-day payload-retention cleanup worker.
- One-minute cron command documentation.
- Audit receipts and operational alerts.

### Acceptance

- Merchant can identify stale/failed processing without database access.
- Invalid-signature/conflicting-replay events cannot be trusted/retried.
- Deterministic failures may be retried after correction.
- Reconciliation detects counter, linkage, queue, and outbox drift.
- Dry-run never mutates data.
- Repair writes audit receipts.

### SQL

No additional SQL expected.

---

## Phase 7 — Canonical campaign and reward event bridge

Expected branch:

```text
feature/pos-sync-event-bridge-v1
```

Depends on: Phases 1–6 merged.

### Scope

- Transactional outbox publisher.
- Publish provider-neutral events:
  - `pos.customer.synced`
  - `pos.customer.match_ambiguous`
  - `pos.purchase.completed`
  - `pos.purchase.identity_enriched`
  - `pos.purchase.refunded`
- Campaign/reward subscription contract.
- Merchant setting for reward-event bridge.
- Event idempotency and retry.
- No provider-specific reward logic.

### Acceptance

- Ledger/CRM commit cannot lose the corresponding outbox event.
- Duplicate worker processing does not republish a business event.
- Provider adapters cannot invoke reward issuance directly.
- Refund event carries enough information for later reward reversal/review rules.

### SQL

No additional SQL expected.

---

## Phase 8 — Shopify POS adapter

Expected branch:

```text
feature/pos-sync-shopify-pos-v1
```

Depends on: Provider-neutral foundation and mature Square pipeline.

### Scope

- Shopify app installation/OAuth.
- Shop/location discovery.
- Customer synchronization.
- Order/payment/refund webhooks.
- Current HMAC verification.
- Verified POS-origin filtering based on selected Shopify API version and fixtures.
- Shopify normalized adapter.
- Privacy/uninstall handling.

### Acceptance

- Online/non-POS orders do not enter POS pipeline unless explicitly approved.
- Shopify deliveries and order updates are idempotent.
- Refunds reconcile with canonical effects.
- Customer matching uses the same core resolver as Square.

### SQL

No additional SQL expected.

---

## Phase 9 — Clover adapter

Expected branch:

```text
feature/pos-sync-clover-v1
```

Depends on: Provider-neutral foundation and mature Square pipeline.

### Scope

- Clover app installation/OAuth.
- Merchant/location mapping.
- Customer/order/payment platform webhooks.
- Provider object retrieval.
- Clover signature/replay contract.
- Line-item and refund/void normalization.
- Clover fixtures.

### Acceptance

- Normal Clover platform webhooks are not confused with Hosted Checkout webhooks.
- Payment/order/customer objects converge into one canonical transaction/customer identity.
- Duplicate and out-of-order events are safe.

### SQL

No additional SQL expected.

---

## Phase 10 — Toast, Lightspeed, governance, and production hardening

Expected branch:

```text
feature/pos-sync-production-hardening-v1
```

Depends on: Prior provider-neutral and Square production readiness.

### Scope

- Confirm Toast partner access and implement supported closed-check adapter.
- Identify correct Lightspeed product family and adapter scope.
- High-volume quick-service tests.
- Multi-provider and multi-location load tests.
- Privacy/retention and consent audit.
- Credential rotation/key-version audit.
- Entitlement/billing hooks.
- Selected-merchant pilot rollout.
- Full production deployment and rollback documentation.
- Final reconciliation suite and closeout report.

### Acceptance

- One-minute worker handles expected merchant burst volume.
- Provider outage/retry storms do not corrupt or duplicate business effects.
- Token expiration/revocation creates actionable merchant state.
- Privacy erasure preserves required financial evidence while removing identity.
- Production pilot has zero unexplained reconciliation drift.

### SQL

Only if implementation discovers a documented correction or a provider requires additional neutral schema.

## Recommended merge order

```text
Phase 1  Provider-neutral foundation
Phase 2  Square OAuth
Phase 3  Square customers
Phase 4  Square purchases/refunds
Phase 5  CRM/LTV
Phase 6  Operations/reconciliation
Phase 7  Event bridge
Phase 8  Shopify POS
Phase 9  Clover
Phase 10 Toast/Lightspeed/hardening
```

## Global completion definition

The feature is not production-complete until:

1. SQL is imported and verified.
2. OAuth credentials and callback URLs are configured.
3. Square webhook subscriptions and signature key are configured.
4. One-minute worker cron is installed and observed.
5. Customer import succeeds.
6. Completed payment, anonymous payment, identity enrichment, partial refund, and duplicate delivery fixtures pass.
7. CRM rollups and canonical ledger reconcile.
8. Merchant health dashboard is usable.
9. Feature remains limited to selected merchants until pilot approval.
10. Deployment and production verification are confirmed separately.