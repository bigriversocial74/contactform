# Creator Campaign Phase 13A — MCP Read Tools

## Scope

Phase 13A exposes the native Creator Campaign domain to Microgifter inline agents and authorized external MCP clients through a fixed read-only tool catalog.

The Creator Campaign module remains the source of truth. MCP handlers call the signed canonical PHP bridge and never write Creator Campaign tables directly.

## Tools

- `microgifter.creator_campaigns.list`
- `microgifter.creator_campaigns.get`
- `microgifter.creator_campaigns.validate`
- `microgifter.creator_campaigns.analytics.get`
- `microgifter.creator_campaigns.applications.list`
- `microgifter.creator_campaigns.participants.list`
- `microgifter.creator_campaigns.deliverables.list`
- `microgifter.creator_campaigns.submissions.list`
- `microgifter.creator_campaigns.tracking.get`
- `microgifter.creator_campaigns.attributions.list`
- `microgifter.creator_campaigns.earnings.list`
- `microgifter.creator_campaigns.payouts.list`
- `microgifter.creator_campaigns.disputes.list`

## Authorization

Every tool requires its exact grantable read scope. The canonical bridge then revalidates:

1. active MCP client and connection;
2. unexpired connection and current token version;
3. read-only operation ceiling;
4. exact connection scope;
5. merchant workspace ownership or active team membership; or
6. active approved Creator identity with own-record filtering.

Merchant connections cannot read another merchant workspace. Creator connections can read only discoverable campaigns or campaigns connected to their own applications, invitations, participation, assignments, tracking, earnings, payouts, and disputes.

## Privacy boundary

Phase 13A never returns:

- anonymous session, visitor, or request hashes;
- customer email, phone, address, or raw identity records;
- database primary keys;
- submission storage keys or content hashes;
- payout provider references;
- banking or payment credentials.

Tracking output contains authorized source links/codes and aggregate accepted activity. Attribution output contains the canonical decision and conversion type, not customer identity.

## Execution boundary

All tools are annotated read-only and nondestructive. Phase 13A does not enable:

- campaign creation, editing, publication, scheduling, pausing, or cancellation;
- application or participant decisions;
- agreement actions;
- content review decisions;
- attribution overrides;
- earnings approval, rejection, hold, or reversal;
- payout recording or external transfers;
- dispute resolution;
- scheduled or autonomous execution.

## SQL

Import after deploying the code:

`database/20260722_creator_campaign_mcp_read_scopes_v13a_single_install.sql`

The migration adds 11 idempotent, grantable `read` scopes to `mcp_scope_catalog`. It creates no tables and grants no connection automatically. A user must explicitly authorize the scopes through the existing MCP connection flow.

## Validation

- PHP syntax for the canonical bridge and endpoint.
- TypeScript build and MCP Node contracts.
- 13-tool catalog and exact scope filtering.
- Canonical receipt recording.
- Merchant workspace and Creator own-record isolation.
- Anonymous tracking and payout-provider data minimization.
- Phase 1–12 Creator Campaign compatibility.
