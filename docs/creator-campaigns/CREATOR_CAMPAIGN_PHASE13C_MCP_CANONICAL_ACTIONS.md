# Creator Campaign Phase 13C — MCP Approval-Gated Canonical Actions

## Purpose

Phase 13C allows a pre-registered external or inline MCP client to request a bounded Creator Campaign action. The MCP client cannot approve or execute the action.

Every canonical effect requires three distinct records and two separate merchant-owner decisions:

1. The authorized MCP client creates a request under an active owner grant.
2. The merchant owner reviews and explicitly approves the exact sanitized action.
3. The same approving owner separately executes the approved action inside Microgifter.

Execution revalidates the client, connection, workspace, scope, grant, playbook, risk ceiling, frequency, concurrency, amount and quantity limits, target policy, approval evidence, expiration, and fresh native state before calling an existing Creator Campaign service.

## Tool catalog

### Campaign lifecycle

- `microgifter.creator_campaigns.publish`
- `microgifter.creator_campaigns.schedule`
- `microgifter.creator_campaigns.pause`
- `microgifter.creator_campaigns.resume`
- `microgifter.creator_campaigns.complete`
- `microgifter.creator_campaigns.cancel`

### Applications, invitations, agreements, and participants

- `microgifter.creator_campaigns.application.approve`
- `microgifter.creator_campaigns.application.decline`
- `microgifter.creator_campaigns.invitation.send`
- `microgifter.creator_campaigns.agreement.offer`
- `microgifter.creator_campaigns.participant.suspend`
- `microgifter.creator_campaigns.participant.remove`

### Content and attribution review

- `microgifter.creator_campaigns.submission.approve`
- `microgifter.creator_campaigns.submission.request_revision`
- `microgifter.creator_campaigns.submission.reject`
- `microgifter.creator_campaigns.attribution.override`

### Earnings, payout records, and disputes

- `microgifter.creator_campaigns.earning.approve`
- `microgifter.creator_campaigns.earning.hold`
- `microgifter.creator_campaigns.earning.reject`
- `microgifter.creator_campaigns.earning.reverse`
- `microgifter.creator_campaigns.payout.record`
- `microgifter.creator_campaigns.dispute.resolve`

## Exact scopes

- `creator_campaigns:publish`
- `creator_campaign_participants:manage`
- `creator_campaign_agreements:manage`
- `creator_campaign_submissions:review`
- `creator_campaign_attribution:manage`
- `creator_campaign_earnings:manage`
- `creator_campaign_payouts:manage`
- `creator_campaign_disputes:manage`

All eight scopes use operation class `approval_gated`. They are grantable but are not granted automatically to any connection.

## OAuth and grant boundary

Dynamic OAuth registration remains read-only. Approval-gated authority is available only to an administrator pre-registered client whose maximum operation class is `approval_gated`.

The merchant must then authorize the exact scopes on a merchant-workspace connection and activate one or more fixed Creator Campaign playbooks. Arbitrary tool names are not accepted. All approval-gated playbooks require a critical risk ceiling because each fixed catalog contains at least one critical action.

## Request record

An MCP action request creates only:

- an `mcp_automation_runs` row in `waiting_for_approval`
- an `mcp_automation_actions` row in `waiting_for_approval`
- a 24-hour `mcp_creator_campaign_action_approvals` row
- security, audit, event, and invocation evidence

It does not call a native Creator Campaign mutation service.

Each request records the connection, client, grant, merchant workspace, exact scope, playbook, tool, risk, idempotency key, sanitized input, input fingerprint, target resource, current lock/status, fresh-state token, proposed amount, proposed quantity, request reason, and approval expiration.

## Owner approval and execution

The owner workspace is:

`/account-creator-campaign-actions.php`

Approval records the owner decision but performs no canonical effect. Execution is a separate POST action with an explicit confirmation control.

Immediately before execution, Microgifter rechecks:

- approving owner identity
- approval and action status
- approval expiration
- client and connection status/expiration
- workspace access
- active grant and revocation version
- exact tool and scope
- operation and risk ceilings
- target allowlist
- frequency, concurrency, amount, and quantity limits
- target status and optimistic-lock state
- fresh-state token equality

A stale or changed resource requires a new MCP request and a new owner approval.

## Native services

Execution dispatches only through existing native services:

- campaign status service
- application review service
- invitation service
- immutable agreement offer service
- participant transition service
- submission review service
- attribution override service
- native earning decision/reversal service
- internal payout record service
- dispute transition service

The MCP request bridge never updates Creator Campaign domain tables directly.

## Financial and legal exclusions

Phase 13C does not:

- call a payment provider
- access bank or payout credentials
- initiate or settle a transfer
- mark a payout paid
- accept an agreement for a Creator
- publish content to a social network
- execute an action from the external MCP process
- schedule autonomous canonical execution
- permit bounded-auto or prohibited authority

`payout.record` creates an internal Microgifter draft payout record from eligible committed reservations only.

## Evidence and failure behavior

Execution creates an `mcp_action_receipts` attempt before calling the native service. The receipt stores the approval, canonical service/action, before and after state tokens, result reference, amount, quantity, and native result evidence.

Failures are fail-closed. The action and run become failed, the receipt records failure evidence, and no automatic retry is scheduled.

## SQL

Required after merge:

`database/20260722_creator_campaign_mcp_canonical_actions_v13c_single_install.sql`

The migration is additive and idempotent. It creates eight scopes, the owner action-approval table, the native earning-review table, and the merchant earning-management permission.
