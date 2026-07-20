# Microgifter MCP Phase 0 Architecture and Contract Lock

**Status:** implementation prerequisite  
**Version:** 1.0  
**Date:** July 20, 2026  
**Program:** Microgifter Platform Phase 5  
**Base branch audited:** `integration-from-repair-20260628`

## 1. Phase 0 decision

The repository is ready to begin the MCP implementation as a separately deployable TypeScript service under `services/mcp/`, backed by narrow protected PHP bridge contracts and the existing Microgifter database and domain authorities.

The launch sequence remains read-only first. The foundation must nevertheless include durable automation grants, automation and run state contracts, idempotency, approval linkage, action receipts, scheduler/worker interfaces, budgets, revocation and kill switches so that automation is not added later as a parallel subsystem.

No production MCP endpoint is enabled by Phase 0.

## 2. Audited active-root authorities

The repository root is the sole active runtime. The following existing sources are authoritative and must be reused:

| Capability | Canonical source or authority |
|---|---|
| Application/API bootstrap | `includes/app.php`, `api/bootstrap.php`, `api/db.php`, `api/security.php` |
| Database migration order | `config/migrations.php` |
| Catalog identity and URLs | `api/catalog/_catalog.php` |
| Published product projection | `includes/public-product-foundation.php`, `api/public/product.php` |
| Recurring gifting | `user_recurring_gift_programs`, `user_recurring_gift_runs`, `user_gifting_plans` |
| Group gifting | `user_group_gifts`, `user_group_gift_participants`, `user_contact_lists` |
| Workplace/community distribution | `distribution_programs`, products, recipients, allocations and issuance jobs |
| Agent strategies and runs | `api/agents/_execution.php`, `agent_strategies`, `agent_workflow_runs`, `agent_workflow_actions` |
| Approval Center | `api/agents/_workflow.php`, `agent_approval_requests`, `agent_execution_events` |
| Gift and commerce lifecycle | orders, PPPM, Microgift lifecycle and Action Center authorities documented in the active file map |
| Audit and security | `mg_audit`, `mg_security_log`, events and existing execution-event records |
| Phase 4 integration map | `config/task_agent_phase4_release.php` |

## 3. Existing foundations confirmed

The audit confirms these reusable controls:

- owner-scoped and workspace-scoped prepared queries;
- public UUID identity conventions;
- canonical migration manifest validation;
- agent strategy action catalogs and maximum actions per run;
- deterministic run idempotency keys;
- approval-required planning;
- high/critical risk reason requirements;
- owner-isolated approval decisions;
- duplicate decision detection;
- conflicting decision rejection;
- approval expiration;
- worker locking using `FOR UPDATE SKIP LOCKED`;
- action execution events and run reconciliation;
- safe response projections that omit internal IDs and request payloads;
- repository PHP 8.2/8.3, PHPUnit and production-quality gates.

The MCP implementation must build on these controls rather than replace them.

## 4. Canonical service boundary

### Required path

```text
MCP tool or automation action
  -> TypeScript schema and policy gateway
  -> protected internal PHP bridge
  -> reusable canonical PHP query or command service
  -> current Microgifter ownership, permission and policy checks
  -> database authority
```

### Prohibited path

```text
MCP tool
  -> direct Node database query
  -> duplicate SQL and duplicate rules
```

The TypeScript service receives no production database credentials.

### PHP bridge requirements

Each bridge operation must:

- accept only server-to-server authenticated requests;
- use one explicit contract name and version;
- resolve user and workspace context from a verified server assertion, not user input alone;
- validate scopes, permissions, ownership and rollout state again;
- call a canonical service;
- return an allowlisted response;
- create or link a request receipt;
- fail closed without exposing SQL, paths, stack traces or internal IDs.

Browser routes retain session and CSRF controls. MCP bridge authentication never weakens browser protections.

## 5. Initial tool contracts

### `microgifter.account.get_connection_context`

- Operation class: `read`
- Required scope: `profile:read`
- Purpose: return safe connection identity, role, workspace and granted capability context.
- Input: empty object.
- Output: connection public ID, client label, user public identity, selected role, optional workspace public identity, scopes, maximum automation level, expiration and read-only rollout state.
- Prohibited output: email, phone, password data, raw role rows, internal numeric IDs, token material, audit evidence.

### `microgifter.catalog.search`

- Operation class: `read`
- Required scope: `catalog:read`
- Purpose: search currently published catalog products using canonical visibility rules.
- Input: query, optional category/location filters, bounded limit, opaque cursor.
- Output: minimal product cards with public product/version IDs, title, merchant name, value/currency, safe image URL, location summary and public URL.
- Maximum initial page size: 25.
- Prohibited output: unpublished versions, merchant-private metadata, internal IDs, full addresses, inventory internals, financial settlement data.

### `microgifter.catalog.get_item`

- Operation class: `read`
- Required scope: `catalog:read`
- Purpose: return one currently published product through the canonical public product projection.
- Input: public product ID; slug may be accepted only as an additional integrity value.
- Output: an MCP-minimized projection derived from `mg_public_product_load`.
- Prohibited output: database IDs, unpublished content, private merchant fields, raw metadata not explicitly allowlisted.

## 6. Scope and authority matrix

| Operation class | OAuth scope | Existing permission | Durable grant | Human approval |
|---|---:|---:|---:|---:|
| Read | required | required | no | no |
| Monitor | required | required | required when unattended | no external effect |
| Recommend | required | required | required when unattended | no external effect |
| Task | required | required | required when unattended | policy dependent |
| Draft | required | required | required when unattended | review before external effect |
| Approval-gated | required | required | required | always before execution |
| Bounded autonomous | required | required | required | grant and policy dependent |
| Prohibited | unavailable | unavailable | unavailable | unavailable |

OAuth authority cannot exceed the user's current Microgifter permission. A grant cannot exceed the connection. A run cannot exceed the grant. A tool rollout flag can narrow any of them.

## 7. Automation grant contract

A durable grant must include:

- public ID and version;
- authorizing user and optional workspace;
- connection and client;
- maximum operation class;
- allowed tool names and playbook keys;
- allowed trigger families;
- start, expiration and revocation timestamps;
- per-run, per-day, per-program and lifetime amount/quantity limits;
- frequency and concurrency limits;
- product, merchant, campaign, recipient-group and template restrictions;
- approval policy;
- risk ceiling;
- current status and revocation version;
- created, updated and last-used timestamps.

No unattended execution is valid without an active grant, even when a bearer token is valid.

## 8. Trigger, scheduler and worker contract

Phase 1 creates interfaces and state contracts only. Active scheduling follows later rollout gates.

Trigger families:

- manual;
- fixed schedule;
- recurring schedule;
- canonical application event;
- canonical-state condition;
- approved monitor threshold.

Run acquisition must use a lease or row lock. Each run and action must have deterministic idempotency keys. Workers must check cancellation, grant version, connection state, permission state, budget and fresh canonical state immediately before execution.

Retries are bounded. Unknown execution outcomes are not blindly retried. They move to reconciliation or dead-letter state and require operator-safe recovery.

## 9. Approval integration contract

The existing Approval Center remains canonical.

MCP automation actions may create or link approval requests, but they do not create a second approval queue. Approval decisions must preserve:

- owner/workspace isolation;
- risk-level rules;
- reason requirements;
- expiration;
- duplicate decision behavior;
- conflicting decision rejection;
- execution-event history;
- fresh-state and permission validation after approval.

Approval is permission to attempt an action against fresh state, not proof that the action is still valid.

## 10. Data classification

### Public

Published product and public merchant fields already intended for public display.

### User-owned

Records belonging to the authenticated user, minimized to the tool purpose.

### Workspace-private

Merchant aggregate or operational data available only under current workspace permission.

### Restricted

Participant identities, customer contact data, exact private addresses, unpublished products/campaigns, internal notes, approval reasons, fraud signals and staff data.

### Secret

Passwords, OAuth codes, access and refresh tokens, signing/encryption keys, payment credentials, claim/redemption secrets and provider secrets.

Restricted data requires a separately approved tool contract. Secret data is never returned by MCP and is never stored in receipts.

## 11. Threat model lock

The initial implementation must explicitly defend against:

- stolen or replayed bearer tokens;
- token use after pause or revocation;
- cross-user and cross-workspace access;
- scope escalation;
- grant widening;
- stale grant or permission use;
- prompt-injection claims that attempt to bypass rules;
- SQL injection and arbitrary query behavior;
- UUID enumeration;
- oversized or deeply nested payloads;
- malformed cursors;
- output-field leakage;
- error-message leakage;
- rate-limit abuse;
- duplicate delivery and worker retries;
- concurrent runs exceeding budgets;
- approval replay;
- stale-state execution after approval;
- queue poisoning and dead-letter replay;
- internal bridge forgery;
- secrets in logs or receipts.

## 12. Migration design lock

Phase 1 will use one consolidated migration placed after `20260720_task_agent_phase4_v1.sql` in `config/migrations.php`.

Proposed filename:

```text
database/20260720_microgifter_mcp_automation_foundation_v1.sql
```

The migration may create MCP client, connection, scope, automation grant, automation, trigger, run, action, approval-link, idempotency, invocation receipt, action receipt, security event, rate-limit and worker-lease tables.

Requirements:

- additive and import-safe;
- public UUIDs on externally referenced records;
- explicit owner/workspace indexes;
- foreign keys where current authorities are stable;
- unique keys for idempotency and token hashes;
- no live secrets or production credentials;
- one schema migration marker;
- registered immediately after Task Agent Phase 4;
- clean-install and rerun validation.

## 13. Test plan lock

Phase 1 must provide focused tests for:

- JSON-RPC lifecycle and error envelopes;
- tool registry visibility;
- input and output schemas;
- internal development authentication;
- user and workspace isolation;
- published-only catalog behavior;
- missing schema and disabled feature flags;
- request and result limits;
- invocation receipts and security events;
- grant and automation state transitions;
- idempotency uniqueness;
- approval linkage contracts;
- worker lease and retry interfaces;
- migration order and import safety;
- PHP bridge contract equivalence;
- PHP 8.2, PHP 8.3 and TypeScript CI.

## 14. Deployment and rollback lock

Phase 0 deploys documentation and validation only.

Phase 1 remains disabled by default through configuration. No public DNS, external OAuth, scheduled run, draft mutation or write action is enabled.

Rollback rules:

- disable MCP feature flags first;
- stop scheduler and workers before application rollback;
- revoke or pause connections and grants when integrity is uncertain;
- preserve additive audit, receipt and automation records;
- never delete canonical Microgifter commerce or gifting data during MCP rollback;
- restore the prior application/service release and verify existing agent, commerce, approval and lifecycle flows.

## 15. Phase 0 acceptance criteria

Phase 0 is complete when:

1. The platform specification is stored in the repository.
2. The canonical service map identifies existing authorities and prohibited duplication.
3. Initial tool schemas and output boundaries are locked.
4. Scope, permission, workspace and grant relationships are explicit.
5. Automation lifecycle, trigger, scheduler, worker, approval and idempotency contracts are explicit.
6. Data classification and threat models are recorded.
7. One consolidated migration design is defined.
8. The test, deployment and rollback plans are defined.
9. A focused validator and CI workflow enforce these artifacts.
10. No runtime endpoint or SQL is introduced by Phase 0.
