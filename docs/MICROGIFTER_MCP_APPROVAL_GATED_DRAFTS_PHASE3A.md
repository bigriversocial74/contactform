# Microgifter MCP Approval-Gated Drafts — Phase 3A

## Purpose

Phase 3A lets an explicitly authorized external MCP client prepare reviewable Microgifter drafts without performing the underlying business action.

Supported draft types:

- gift;
- campaign;
- reward;
- message.

An approved draft remains a stored review record. Approval does not create, publish, send, purchase, issue, deliver, schedule, activate, fulfill, or execute anything.

## Required SQL

Import through the canonical migration runner:

```text
database/20260720_mcp_approval_gated_drafts_phase3a_v1.sql
```

The migration creates:

- `mcp_agent_drafts`;
- `mcp_agent_draft_events`;
- active, grantable `gift:draft`, `campaign:draft`, `reward:draft`, and `message:draft` scopes.

It does not add a foreign key or queue path to `agent_workflow_actions`, `agent_workflow_runs`, `mcp_automation_actions`, or `mcp_automation_runs`.

## Operation classes

OAuth clients may be preregistered with one of two ceilings:

- `read` — account and catalog tools only;
- `draft` — read tools plus explicitly granted reviewable-draft scopes.

Dynamic OAuth registration remains `read` only. An administrator must preregister a client before it can request draft scopes.

## MCP tools

Creation tools:

```text
microgifter.gift.create_draft
microgifter.campaign.create_draft
microgifter.reward.create_draft
microgifter.message.create_draft
```

Draft management tools:

```text
microgifter.drafts.list
microgifter.drafts.get
microgifter.drafts.cancel
```

There are no publish, send, purchase, payment, delivery, schedule, activation, fulfillment, or execution tools in Phase 3A.

## Workspace rules

- Gift drafts may use an account or merchant connection.
- Campaign, reward, and message drafts require an authorized merchant workspace.
- Every tool call revalidates the live connection, OAuth scopes, token version, user status, client status, workspace relationship, and operation ceiling through the PHP authority.
- Revoking a draft scope blocks subsequent list, get, create, and cancel access for that draft type.

## Idempotency

Every creation call requires an idempotency key scoped to the MCP connection.

- Repeating the same key with the same type and canonical payload returns the original draft.
- Reusing the key with different content returns a conflict.
- Raw arbitrary JSON is not accepted. Each draft type has a strict field whitelist and size limits.

## Human review

Users review drafts at:

```text
/account-agent-drafts.php
```

The owner can:

- approve a pending draft;
- reject a pending draft;
- inspect its sanitized payload and reason;
- view status and client/connection context.

Owner decisions are CSRF-protected and owner-scoped. Pending drafts expire after seven days.

Approval produces:

```text
status: approved
execution.enabled: false
execution.status: not_enabled
execution.next_step: manual_microgifter_follow_up
```

A future phase must add a separate, explicitly authorized conversion workflow before approved drafts can become live Microgifter objects.

## Client registration

Open:

```text
/admin/mcp-oauth-clients.php
```

Choose **Read + reviewable drafts**, then preregister the exact callback URL. During consent, request only the draft scopes needed by that client and workspace.

Recommended first pilot scopes:

```text
profile:read
catalog:read
gift:draft
```

Add merchant draft scopes only for a merchant-workspace connection.

## Validation

Node:

```bash
cd services/mcp
npm ci --ignore-scripts
npm audit --audit-level=high
npm run check
```

PHP contract:

```bash
vendor/bin/phpunit tests/phpunit/McpApprovalGatedDraftsPhase3aV1ContractTest.php
```

Clean-database lifecycle:

```bash
php scripts/run_migrations.php
php scripts/test_mcp_approval_gated_drafts_phase3a.php
```

The executable lifecycle asserts that both execution queue row counts remain unchanged.

## Deployment order

1. Deploy the merged application files.
2. Import the Phase 3A migration.
3. Keep public OAuth disabled until the Node VPS is available.
4. When the VPS is ready, follow `docs/MICROGIFTER_MCP_INSTALLATION_AND_ACTIVATION.md`.
5. Pre-register a draft-capable client.
6. Connect one internal pilot user with minimal scopes.
7. Verify draft creation and owner review.
8. Confirm approval does not create rows in either execution queue.
9. Only then expand draft scopes to additional clients or workspaces.

## Not included

- conversion of an approved draft into a live gift, campaign, reward, or message;
- publishing or scheduling;
- payments or purchases;
- gift issuance or delivery;
- message delivery;
- reward activation or fulfillment;
- worker execution;
- autonomous actions.
