# Microgifter MCP Automation Platform Specification

**Version:** 1.1  
**Date:** July 20, 2026  
**Program:** Microgifter Platform Phase 5  
**Repository:** `bigriversocial74/contactform`  
**Integration branch:** `integration-from-repair-20260628`  
**Primary service:** `services/mcp/` TypeScript / Node.js  
**Launch posture:** remote and authenticated; read-only first; automation-capable by design

## 1. Executive decision

Microgifter will provide a remote MCP server that allows approved external and internal agent harnesses to discover Microgifter capabilities, read canonical state, create plans and drafts, request actions, and manage durable automations.

The first release remains read-only. The architecture, database design, authorization model, scheduler interfaces, approval contracts, budgets, idempotency, action receipts, cancellation, and kill switches must support bounded automation from the first implementation phase.

Full automation does not mean unrestricted execution. Every unattended action must be tied to:

- an active OAuth connection;
- a Microgifter user;
- one merchant workspace when applicable;
- a durable automation grant;
- explicit scopes and existing Microgifter permissions;
- an approved tool and playbook;
- budgets, frequency, quantity, recipient, product, campaign, and expiration limits;
- action-specific approval requirements;
- current canonical Microgifter state.

There is no administrator bypass, generic database tool, arbitrary command tool, arbitrary URL fetcher, or unbounded autonomous mode.

## 2. Core authority rule

> An authorized agent may request or configure an approved Microgifter operation. Microgifter remains the system of record, execution authority, and final authority for identity, ownership, eligibility, campaign rules, reward rules, gift state, claims, redemption, payments, permissions, and commerce outcomes.

The MCP layer is an interoperability and policy gateway. It must not duplicate domain rules or become a second commerce, campaign, reward, gifting, approval, claim, redemption, or financial system.

## 3. Platform responsibilities

The platform has two coordinated responsibilities:

1. **Interactive MCP gateway** — handles Streamable HTTP, JSON-RPC lifecycle, OAuth-protected requests, `tools/list`, and `tools/call`.
2. **Durable automation control plane** — stores grants and definitions, schedules or triggers runs, routes proposed actions through policy and approvals, executes approved canonical operations, and records immutable receipts.

An external MCP client may disconnect after creating an automation. Continued execution occurs inside Microgifter workers under the durable grant, never under an indefinitely trusted bearer token.

## 4. High-level architecture

```text
MCP client
  -> HTTPS / Streamable HTTP
  -> TypeScript MCP gateway
  -> connection, scope, workspace, entitlement and rollout guards
  -> tool registry and input schemas
  -> protected internal PHP bridge
  -> canonical Microgifter query or command service
  -> existing permissions, ownership, policy and database authority
  -> filtered MCP result and invocation receipt

Durable automation
  -> automation definition and grant
  -> scheduler or canonical event trigger
  -> queue and worker
  -> fresh-state, budget, risk and approval policy
  -> canonical Microgifter command service
  -> action receipt, metrics and security events
```

### Service boundaries

- `services/mcp/` owns MCP transport, JSON-RPC, schemas, registry, OAuth resource validation, automation scheduling coordination, and gateway-level observability.
- PHP remains the canonical domain boundary for the current application.
- Protected internal bridge routes expose narrow query and command contracts only.
- The Node service never receives database credentials and never queries Microgifter tables directly.
- Browser endpoints retain their existing session and CSRF protections.

## 5. Operation classes

Every tool and playbook has one operation class:

- `read` — returns privacy-filtered canonical state.
- `monitor` — evaluates canonical state and reports attention items.
- `recommend` — returns a proposed next step without creating a durable object.
- `task` — creates or updates an internal task only.
- `draft` — creates a reviewable draft with no external effect.
- `approval_gated` — creates an action request that cannot execute before approval and fresh-state revalidation.
- `bounded_auto` — executes without per-run approval only within an explicit grant and approved playbook.
- `prohibited` — unavailable through MCP.

Claim verification, redemption, refunds, settlements, permission changes, credential management, destructive administration, unrestricted CRM export, and arbitrary database operations remain prohibited until a separately approved specification says otherwise.

## 6. Connection and grant model

OAuth scope is necessary but never sufficient.

Every call must pass:

```text
active client
AND active connection
AND valid audience-bound token
AND required OAuth scope
AND current Microgifter permission
AND workspace membership or ownership
AND entitlement
AND tool rollout flag
AND data policy
```

Every unattended run must additionally pass:

```text
active automation
AND active automation grant
AND allowed playbook and tool
AND trigger validity
AND schedule validity
AND budget and quantity limits
AND recipient/product/campaign/template restrictions
AND risk policy
AND approval policy
AND fresh-state token
AND idempotency and concurrency guard
```

A grant records the authorizing user, workspace, maximum operation class, allowed tools and playbooks, start and expiration time, budgets, frequency, quantities, allowed targets, approval policy, and revocation version.

Revoking the connection or grant prevents new and queued execution.

## 7. Automation lifecycle

Automation states:

```text
draft
pending_approval
active
paused
completed
failed
expired
revoked
```

Run states:

```text
queued
evaluating
waiting_for_approval
approved
executing
succeeded
partially_succeeded
failed
cancelled
dead_lettered
```

Actions use deterministic idempotency keys, row or lease locking, bounded retries, exponential backoff, cancellation checks, fresh-state validation, and one durable receipt for every attempt.

Repeated failures, budget anomalies, permission changes, grant changes, stale state, worker recovery uncertainty, or suspicious activity automatically pause the automation or action according to policy.

## 8. Trigger model

Supported trigger families are introduced through staged rollout:

- manual;
- fixed schedule;
- recurring calendar schedule;
- canonical Microgifter event;
- condition evaluated from canonical state;
- approved monitoring threshold.

External agents cannot provide unverified statements as trigger evidence. Campaign completion, reward eligibility, purchases, claims, redemption, approvals, and other consequential facts must come from canonical Microgifter records.

## 9. Initial MCP tools

The first implementation exposes only internal-development, read-only tools:

- `microgifter.account.get_connection_context`
- `microgifter.catalog.search`
- `microgifter.catalog.get_item`

These tools must use canonical PHP query services, output allowlists, maximum result counts, cursor validation, rate limits, request IDs, and invocation receipts.

Later read-only tools may cover merchants, campaigns, rewards, gifts, recurring programs, group gifts, distribution programs, approvals, monitoring, and lifecycle status.

## 10. Automation-ready data model

The consolidated MCP foundation migration will introduce tables or equivalent repositories for:

- clients;
- connections;
- authorization codes and token families;
- connection scopes;
- automation grants;
- automations;
- automation triggers;
- automation runs;
- automation actions;
- approval links or approval records;
- idempotency keys;
- invocation receipts;
- action receipts;
- security events;
- rate-limit buckets;
- worker leases and dead-letter records where required.

No production token, signing key, encryption key, password, payment credential, claim secret, or provider secret is seeded by SQL.

## 11. Canonical Microgifter reuse

The MCP build must reuse the repository's existing authorities, including:

- application bootstrap and database connection;
- role and permission matrix;
- merchant workspaces and staff scoping;
- public UUID conventions;
- published catalog and product-version authority;
- public product projection;
- campaigns and reward eligibility records;
- recurring gifting programs and runs;
- pledge-only group gifts;
- merchant distribution programs;
- agent strategies, workflow runs, actions and approvals;
- gift plans, orders, PPPM, Microgift lifecycle and Action Center;
- audit and security logging;
- migration manifest and PHP 8.2/8.3 validation.

When business logic currently lives in an HTTP controller, the authoritative operation must be extracted into a reusable service. Existing endpoints and MCP bridge adapters then call the same service and receive the same policy result.

## 12. Security boundaries

Mandatory controls include:

- TLS only;
- OAuth on every public MCP request;
- exact audience validation;
- strict origin and redirect validation;
- no query-string tokens;
- strict JSON schemas and request-size limits;
- output field allowlists and result caps;
- connection, user, workspace, scope, permission, entitlement and grant validation per call;
- prepared statements inside canonical PHP services;
- no arbitrary SQL, file path, shell command, callback, webhook or URL tools;
- no secret fields in responses or logs;
- rate limits by client, connection, user, IP hash and tool;
- revocation checked before each call and run;
- independent rollout flags per tool and operation class;
- fail-closed behavior when required schema, secrets, queue, worker, grant, bridge or canonical authority is unavailable.

Models and MCP clients are untrusted callers. Natural-language claims never override validated Microgifter state.

## 13. Testing requirements

Required coverage includes:

- Streamable HTTP and JSON-RPC lifecycle;
- protocol-version negotiation;
- `tools/list` visibility by connection and scope;
- tool input and output schema validation;
- OAuth Authorization Code with PKCE, rotation, revocation and replay detection;
- user, merchant, workspace and record isolation;
- missing-schema and missing-secret fail-closed behavior;
- SQL injection, UUID enumeration, oversized payload, malformed cursor, invalid origin and rate-limit enforcement;
- automation state transitions;
- schedule, trigger, retry, cancellation and dead-letter behavior;
- grant narrowing and revocation;
- budget, quantity, frequency, target and expiration enforcement;
- approval-before-execution and fresh-state revalidation;
- action idempotency and duplicate-delivery recovery;
- PHP/TypeScript contract equivalence;
- PHP 8.2, PHP 8.3, TypeScript, repository Golden Path and production-quality gates.

## 14. Internal MCP phases

### Phase 0 — Architecture and contract lock

Audit the latest integration branch, map canonical services, lock TypeScript/PHP boundaries, operation classes, schemas, scopes, data classification, threats, migration design, test plan and runbooks. No production endpoint.

### Phase 1 — Protocol, read-only core and automation foundation

Create the TypeScript service, Streamable HTTP/JSON-RPC lifecycle, registry, internal authentication, three read-only tools, automation-ready migration, receipts, rate limits, repositories and scheduler/worker interfaces. External execution remains disabled.

### Phase 2 — OAuth and connection management

Add protected-resource metadata, Authorization Code with PKCE, token rotation, consent, pause, revoke, activity and admin client registry.

### Phase 3 — External read-only pilot

Allowlisted ChatGPT, Claude and custom-client compatibility, security, monitoring, load and abuse validation.

### Phase 4 — Monitor, recommend, task and draft automations

Add durable grants, schedules and canonical-event triggers, queue/worker processing, control center, monitoring, recommendations, tasks, drafts, simulations and run history. No external-effect action without approval.

### Phase 5 — Approval-gated actions

Integrate the existing Approval Center, fresh-state validation, action receipts and individually approved gift, group-gift, campaign, message and reward actions. Human approval is required for every external effect.

### Phase 6 — Bounded autonomous programs

Enable individually approved low-to-moderate-risk playbooks under explicit grants, budgets, target restrictions, circuit breakers, anomaly pauses and merchant policy.

### Phase 7 — Production hardening and broad access

Production subdomain, TLS, WAF, worker scaling, queue recovery, key rotation, disaster recovery, documentation, compatibility certification, kill-switch validation, rollback validation and final 10/10 gate.

## 15. First implementation boundary

The first implementation PR after Phase 0 may include:

- `services/mcp/` TypeScript scaffold;
- strict TypeScript and test configuration;
- Streamable HTTP and JSON-RPC router;
- internal development token/context boundary;
- tool registry;
- the three initial read-only tools;
- protected canonical PHP query bridge;
- the consolidated automation foundation migration;
- invocation receipts, security events and rate limits;
- automation grant, automation, run, approval, receipt and idempotency state contracts;
- scheduler, worker, queue, policy and command-bridge interfaces;
- feature flags disabled by default;
- focused protocol, isolation, migration and state-contract tests.

It must not enable external OAuth, scheduled execution, draft actions, approval-gated writes or bounded autonomy. Those capabilities follow in separate scoped PRs.
