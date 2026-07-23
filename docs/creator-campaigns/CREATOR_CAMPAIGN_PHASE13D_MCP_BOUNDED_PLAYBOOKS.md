# Creator Campaign Phase 13D — MCP Bounded Automation Playbooks

## Purpose

Phase 13D packages existing Creator Campaign read and draft capabilities into six fixed, owner-configured playbooks. Each successful run creates one non-convertible review artifact in the existing Agent Drafts workspace and one canonical automation receipt.

Phase 13D is not autonomous execution. It does not create an approval-gated canonical action request and cannot mutate a native Creator Campaign record.

## Initial playbooks

| MCP tool | Required scope | Fixed playbook |
|---|---|---|
| `microgifter.creator_campaigns.playbooks.campaign_preparation.run` | `creator_campaign_playbooks:campaign_preparation` | `creator_campaign_campaign_preparation` |
| `microgifter.creator_campaigns.playbooks.application_review.run` | `creator_campaign_playbooks:application_review` | `creator_campaign_application_review` |
| `microgifter.creator_campaigns.playbooks.content_review.run` | `creator_campaign_playbooks:content_review` | `creator_campaign_content_review` |
| `microgifter.creator_campaigns.playbooks.campaign_health.run` | `creator_campaign_playbooks:campaign_health` | `creator_campaign_health` |
| `microgifter.creator_campaigns.playbooks.earnings_review.run` | `creator_campaign_playbooks:earnings_review` | `creator_campaign_earnings_review` |
| `microgifter.creator_campaigns.playbooks.creator_outreach.run` | `creator_campaign_playbooks:creator_outreach` | `creator_campaign_creator_outreach` |

Tool discovery is exact-scope filtered and requires a draft-authority merchant-workspace connection.

## Owner configuration gate

A playbook run requires all of the following:

1. An active MCP client and connection with a `draft` operation ceiling.
2. Every scope required by the selected fixed playbook.
3. An active owner-created automation grant containing that playbook and tool.
4. An active automation definition tied to the same connection, merchant workspace, grant, and fixed playbook key.
5. An active manual trigger.
6. A valid idempotency key and, when configured, an allowed Creator Campaign target.

Existing connections receive no Phase 13D scopes automatically.

## Run output

Each successful run records:

- one `mcp_automation_runs` row
- one succeeded `mcp_automation_actions` row
- one succeeded `mcp_action_receipts` row
- one pending-review `mcp_agent_drafts` artifact
- immutable payload and input fingerprints
- the active grant and automation versions
- audit, domain-event, and security evidence

The artifact is marked as a Creator Campaign playbook output and cannot enter native draft conversion.

## Playbook boundaries

### Campaign preparation assistant

- Reuses Phase 13B campaign, product, eligibility, deliverable, and compensation proposal validation.
- May compare proposed values with an existing merchant-owned campaign.
- Creates no campaign and cannot publish or schedule.

### Creator application review assistant

- Reads one pending merchant-scoped application.
- Drafts a recommendation, fit score, eligibility notes, missing information, and optional response text.
- Cannot approve, decline, or send a message.

### Content review assistant

- Reads the canonical submission and deliverable.
- Evaluates disclosure, links, talking points, prohibited claims, and required changes.
- Reuses the Phase 13B submission-feedback draft contract.
- Cannot approve, reject, or request a revision.

### Campaign health assistant

- Aggregates validation, analytics, applications, participants, deliverables, submissions, earnings, payouts, and disputes.
- Produces a reviewable risk report and proposed follow-up list.
- Creates no canonical action request.

### Earnings review assistant

- Reads the canonical earning, attribution, payout, and dispute evidence.
- Drafts an approve, hold, reject, or reverse recommendation.
- Failed verification checks force the server recommendation to `hold`.
- Cannot change an earning, create a payout, call a payment provider, or move money.

### Creator outreach assistant

- Accepts only active approved Microgifter Creator profile IDs.
- Verifies current application, invitation, and participant relationships for the campaign.
- Blocks candidates who already have an active or pending relationship.
- Produces ranked invitation drafts only and cannot send them.

## Explicit exclusions

Phase 13D cannot:

- run from a schedule, worker, queue, condition, or event trigger
- publish, schedule, pause, resume, complete, or cancel a campaign
- approve or decline applications
- send invitations or messages
- create or change agreements
- approve, reject, or request revisions on submissions
- change attribution
- approve, hold, reject, or reverse an earning
- record or issue a payout
- resolve a dispute
- call a payment provider
- create a Phase 13C canonical action request
- execute any external effect

Any later canonical action remains a separate Phase 13C request with explicit merchant approval and separate owner execution.

## SQL

Import after merge and before granting Phase 13D scopes:

`database/20260722_creator_campaign_mcp_bounded_playbooks_v13d_single_install.sql`

The migration is additive and idempotent. It adds six grantable `draft` scopes and grants none automatically.
