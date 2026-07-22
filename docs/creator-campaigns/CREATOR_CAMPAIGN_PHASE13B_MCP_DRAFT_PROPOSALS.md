# Creator Campaign Phase 13B — MCP Draft and Proposal Tools

## Purpose

Phase 13B allows an authorized external or inline agent to prepare structured Creator Campaign proposals for merchant review. Every proposal is stored in the existing `mcp_agent_drafts` review ledger and remains non-executing.

Phase 13B does not create or modify native Creator Campaign records. Approval records merchant acceptance of the proposal only and does not enable conversion, publication, messaging, participant decisions, agreements, compensation, earnings, payouts, disputes, scheduling, or automation.

## MCP tools

| Tool | Scope | Minimum risk |
|---|---|---|
| `microgifter.creator_campaigns.draft.create` | `creator_campaigns:draft` | medium |
| `microgifter.creator_campaigns.draft.update` | `creator_campaigns:draft` | medium |
| `microgifter.creator_campaigns.products.propose` | `creator_campaign_products:draft` | medium |
| `microgifter.creator_campaigns.eligibility.propose` | `creator_campaign_eligibility:draft` | medium |
| `microgifter.creator_campaigns.deliverables.propose` | `creator_campaign_deliverables:draft` | medium |
| `microgifter.creator_campaigns.compensation.propose` | `creator_campaign_compensation:draft` | high |
| `microgifter.creator_campaigns.attribution.propose` | `creator_campaign_attribution:draft` | medium |
| `microgifter.creator_campaigns.budget.propose` | `creator_campaign_budget:draft` | high |
| `microgifter.creator_campaigns.rights.propose` | `creator_campaign_rights:draft` | high |
| `microgifter.creator_campaigns.terms.propose` | `creator_campaign_terms:draft` | high |
| `microgifter.creator_campaigns.invitation.draft` | `creator_campaign_invitations:draft` | medium |
| `microgifter.creator_campaigns.message.draft` | `creator_campaign_messages:draft` | medium |
| `microgifter.creator_campaigns.submission_feedback.draft` | `creator_campaign_submission_feedback:draft` | medium |

Tool discovery is scope-filtered. A connection must have maximum operation class `draft`, an active grantable scope, an active client and connection, and an authorized merchant workspace.

## Canonical proposal envelope

Each proposal records:

- MCP connection and client
- requesting user and merchant workspace
- exact required scope
- proposal kind and proposed action
- referenced campaign when applicable
- normalized proposed values
- risk classification
- merchant-owner approval requirement
- seven-day review expiration
- source request and idempotency keys
- payload fingerprint and immutable draft events
- explicit execution and conversion boundaries

Referenced campaign, product, product version, Creator profile, participant, and submission identifiers are revalidated against canonical ownership rules before the proposal is accepted.

## Review and conversion boundary

Creator Campaign proposals appear in the existing Agent Drafts workspace. Merchants may approve or reject them. Approved proposals display **Awaiting approval-gated canonical actions**.

The existing native-draft conversion action rejects proposals marked `creator_campaign_proposal`. No Phase 13B proposal can create an inactive native campaign or any other native object. Phase 13C must implement separately authorized canonical actions before approved proposal values can be applied.

## Explicit exclusions

Phase 13B cannot:

- publish, schedule, pause, complete, cancel, or otherwise change a campaign
- attach products or change eligibility/deliverables
- invite, approve, decline, suspend, or remove a Creator
- send or schedule messages
- approve, reject, or request revisions on submissions
- create or alter agreements or accepted terms
- activate compensation or attribution rules
- reserve or spend budget
- create, approve, hold, reject, reverse, or pay earnings
- record payouts or resolve disputes
- trigger automation or external effects

## SQL

Import after merge and before granting Phase 13B scopes:

`database/20260722_creator_campaign_mcp_draft_scopes_v13b_single_install.sql`

The migration is additive and idempotent. It adds twelve grantable `draft` scopes and grants none automatically to existing connections.
