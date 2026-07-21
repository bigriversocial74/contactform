# Microgifter Brand–Creator Campaign System
## MCP Integration Amendment

## 1. Recommended implementation sequence

The Creator Campaign module should be built first as a complete native Microgifter domain, while the MCP integration contract is defined in parallel.

Recommended sequence:

```text
1. Define MCP contract now
2. Build native Creator Campaign services and permissions
3. Validate canonical module actions
4. Add read-only MCP tools
5. Add approval-gated MCP draft actions
6. Add bounded owner grants and automation playbooks
7. Add scheduled or event-triggered simulations
8. Enable canonical execution only after production validation
```

The MCP should not become the business-logic layer for Creator Campaigns.

The Creator Campaign module remains the canonical source of truth. MCP tools call the same native services used by the Microgifter interface and API.

## 2. Core architecture principle

```text
External agent or Microgifter inline agent
→ MCP connection
→ Owner grant
→ Fixed tool or playbook
→ Permission and scope validation
→ Approval gate
→ Creator Campaign service
→ Canonical database transaction
→ Action receipt
→ Audit and security evidence
```

The MCP exposes bounded access to the module. It does not bypass:

- Merchant workspace ownership
- Creator approval requirements
- Campaign state rules
- Agreement acceptance
- Attribution rules
- Budget limits
- Compensation limits
- Content review requirements
- Earnings approval
- Payout controls
- Audit requirements

## 3. Why the module should be built first

The native module must establish stable canonical operations before external agents can safely invoke them.

The module build defines:

- Campaign states
- Participant states
- Agreement versions
- Deliverable states
- Submission review actions
- Tracking sources
- Attribution decisions
- Compensation calculations
- Budget reservations
- Earnings transitions
- Payout records
- Dispute and fraud holds

Without stable native services, the MCP would duplicate or invent business rules. That would create inconsistent behavior between the web interface, API, inline agents, and external agent harnesses.

## 4. What should be designed before the module build

Although canonical execution comes later, the following MCP contract should be agreed before implementation:

- Tool names
- Tool descriptions
- Input schemas
- Output schemas
- Required scopes
- Risk classifications
- Approval requirements
- Owner grant limits
- Idempotency rules
- Action receipt format
- Audit-event format
- Error codes
- Which tools are read-only, draft-only, approval-gated, or prohibited

This prevents the native service layer from being built in a way that is difficult to expose safely later.

## 5. MCP integration phases

### Phase A — Read-only discovery

Read-only tools may be added after the module's query services are stable.

Recommended tools:

```text
creator_campaigns.list
creator_campaigns.get
creator_campaigns.validate
creator_campaigns.analytics.get
creator_campaigns.applications.list
creator_campaigns.participants.list
creator_campaigns.deliverables.list
creator_campaigns.submissions.list
creator_campaigns.tracking.get
creator_campaigns.attributions.list
creator_campaigns.earnings.list
creator_campaigns.payouts.list
creator_campaigns.disputes.list
```

Read-only tools should still enforce:

- Active MCP connection
- Active client
- Active owner grant
- Required scopes
- Merchant workspace membership
- Resource ownership
- Data minimization

Creator-facing MCP clients may access only the authenticated creator's own records.

### Phase B — Draft and proposal tools

Draft tools may create proposals or editable drafts but must not publish, approve, pay, or create external effects.

Recommended tools:

```text
creator_campaigns.draft.create
creator_campaigns.draft.update
creator_campaigns.products.propose
creator_campaigns.eligibility.propose
creator_campaigns.deliverables.propose
creator_campaigns.compensation.propose
creator_campaigns.attribution.propose
creator_campaigns.budget.propose
creator_campaigns.rights.propose
creator_campaigns.terms.propose
creator_campaigns.invitation.draft
creator_campaigns.message.draft
creator_campaigns.submission_feedback.draft
```

Draft outputs should record:

- MCP connection
- MCP client
- Grant
- Automation definition when applicable
- Requesting user
- Merchant workspace
- Proposed action
- Proposed values
- Risk level
- Required approval
- Expiration
- Idempotency key

No draft tool should silently mutate accepted agreements, active compensation rules, earnings, or payouts.

### Phase C — Approval-gated canonical actions

Canonical actions should be enabled only after the native module and draft workflows are production-tested.

Recommended approval-gated actions:

```text
creator_campaigns.publish
creator_campaigns.schedule
creator_campaigns.pause
creator_campaigns.resume
creator_campaigns.complete
creator_campaigns.cancel
creator_campaigns.application.approve
creator_campaigns.application.decline
creator_campaigns.invitation.send
creator_campaigns.agreement.offer
creator_campaigns.participant.suspend
creator_campaigns.participant.remove
creator_campaigns.submission.approve
creator_campaigns.submission.request_revision
creator_campaigns.submission.reject
creator_campaigns.attribution.override
creator_campaigns.earning.approve
creator_campaigns.earning.hold
creator_campaigns.earning.reject
creator_campaigns.earning.reverse
creator_campaigns.payout.record
creator_campaigns.dispute.resolve
```

Every canonical action must call the native Creator Campaign service rather than writing tables directly.

### Phase D — Bounded automation playbooks

After canonical actions are stable, fixed MCP playbooks may combine approved tools.

Recommended initial playbooks:

#### Campaign preparation assistant

```text
Read merchant products
→ Create campaign draft
→ Propose eligibility
→ Propose deliverables
→ Propose compensation
→ Run validation
→ Return approval-ready draft
```

No automatic publication.

#### Creator application review assistant

```text
Read pending applications
→ Compare eligibility
→ Summarize creator profile and portfolio
→ Identify missing information
→ Draft approval, decline, or information request
```

Merchant approval remains required.

#### Content review assistant

```text
Read deliverable brief
→ Read submission
→ Check talking points, disclosure, links, and prohibited claims
→ Draft approval or revision response
```

No automatic rejection or approval initially.

#### Campaign health assistant

```text
Read campaign status
→ Read budget, deliverables, agreements, earnings, and disputes
→ Identify risks
→ Draft recommended actions
```

Read-only by default.

#### Earnings review assistant

```text
Read earning evidence
→ Verify agreement version
→ Verify attribution
→ Verify compensation rule
→ Check budget, refund, fraud, and duplicate status
→ Draft approve, hold, reject, or reverse recommendation
```

Financial action always requires explicit owner approval.

#### Creator outreach assistant

```text
Search approved eligible creators
→ Rank by campaign fit
→ Draft invitation list and messages
→ Present for merchant approval
```

Only existing approved Microgifter creators may be selected.

## 6. Scope catalog

Recommended MCP scopes:

### Read scopes

```text
creator_campaigns.read
creator_campaigns.analytics.read
creator_campaigns.applications.read
creator_campaigns.participants.read
creator_campaigns.agreements.read
creator_campaigns.deliverables.read
creator_campaigns.submissions.read
creator_campaigns.tracking.read
creator_campaigns.attribution.read
creator_campaigns.earnings.read
creator_campaigns.payouts.read
creator_campaigns.disputes.read
```

### Draft scopes

```text
creator_campaigns.drafts.write
creator_campaigns.invitations.draft
creator_campaigns.messages.draft
creator_campaigns.reviews.draft
```

### Canonical action scopes

```text
creator_campaigns.publish
creator_campaigns.participants.manage
creator_campaigns.agreements.manage
creator_campaigns.submissions.review
creator_campaigns.attribution.manage
creator_campaigns.earnings.manage
creator_campaigns.payouts.manage
creator_campaigns.disputes.manage
```

High-risk scopes should not be implied by broad write access.

## 7. Risk classification

### Low risk

- List campaigns
- Read campaign configuration
- Read analytics
- Read applications
- Read deliverables
- Read creator performance
- Validate campaign drafts

### Medium risk

- Create or update draft campaigns
- Draft invitations
- Draft messages
- Draft submission feedback
- Propose compensation or budget changes
- Pause an active campaign

### High risk

- Publish or cancel campaigns
- Approve or remove creators
- Offer or terminate agreements
- Approve or reject submissions
- Override attribution
- Approve, reject, or reverse earnings
- Record payouts
- Resolve disputes

High-risk actions should require explicit per-action approval even when a broader automation grant exists.

## 8. Owner grant limits

Creator Campaign MCP grants should support limits for:

- Merchant workspace
- Campaign UUIDs
- Creator UUIDs
- Product UUIDs
- Reward-template UUIDs
- Allowed tool names
- Allowed playbooks
- Maximum risk level
- Maximum campaign budget
- Maximum compensation rate
- Maximum compensation amount
- Maximum invitations per run, day, and lifetime
- Maximum creator approvals
- Maximum earning approvals
- Maximum payout amount
- Minimum trigger frequency
- Maximum concurrent runs
- Expiration

The existing MCP grant evaluator should remain fail-closed.

## 9. Approval policy

Recommended initial policy:

```text
Read tools: no action approval required
Draft tools: draft creation allowed under grant
Canonical nonfinancial actions: explicit owner approval
Financial actions: explicit owner approval for every action
Agreement and rights changes: explicit owner approval
Attribution overrides: explicit owner approval with reason
```

An automation grant authorizes the agent to propose or request an action. It does not remove the native module's approval requirements.

## 10. Canonical service mapping

MCP tools should call the same services used by native routes.

```text
creator_campaigns.*
→ CreatorCampaignService

creator_campaigns.applications.*
→ CreatorApplicationService

creator_campaigns.invitations.*
→ CreatorInvitationService

creator_campaigns.participants.*
→ CreatorParticipantService

creator_campaigns.agreements.*
→ CreatorAgreementService

creator_campaigns.deliverables.*
→ CreatorDeliverableService

creator_campaigns.submissions.*
→ CreatorSubmissionService

creator_campaigns.tracking.*
→ CreatorTrackingService

creator_campaigns.attributions.*
→ CreatorAttributionService

creator_campaigns.compensation.*
→ CreatorCompensationService

creator_campaigns.budget.*
→ CreatorBudgetService

creator_campaigns.earnings.*
→ CreatorEarningsService

creator_campaigns.payouts.*
→ CreatorPayoutService

creator_campaigns.disputes.*
→ CreatorDisputeService
```

MCP handlers must not contain duplicate compensation, attribution, agreement, or budget logic.

## 11. Action receipts and audit evidence

Every canonical MCP action should create an action receipt containing:

- MCP connection ID
- MCP client ID
- Grant ID
- Automation definition ID when applicable
- Automation run ID when applicable
- Tool name
- Playbook name when applicable
- Requesting user
- Approving user
- Merchant workspace
- Campaign
- Participant or creator when applicable
- Native service action
- Native record IDs
- Before state
- After state
- Approval evidence
- Idempotency key
- Result
- Error code when failed
- Timestamp

The native module audit log remains authoritative for the business action. MCP security events remain authoritative for connection, scope, grant, and automation evidence.

## 12. Scheduling and event triggers

The current MCP scheduled-simulation layer may be used during module development to test Creator Campaign playbooks without canonical effects.

Recommended development sequence:

```text
Fixed or recurring trigger
→ Revalidate grant
→ Read Creator Campaign test data
→ Generate proposed actions
→ Record simulation run
→ Create no canonical action receipts
```

After production readiness, selected playbooks may become approval-gated scheduled runs.

Recommended safe scheduled use cases:

- Daily campaign health summary
- Approaching deadline report
- Pending application summary
- Content-review queue summary
- Budget warning report
- Earnings-review queue summary
- Dispute-aging report

Do not initially schedule automatic:

- Campaign publication
- Creator approval or removal
- Agreement offers or termination
- Submission approval or rejection
- Attribution overrides
- Earnings approval or reversal
- Payout recording

## 13. Inline agents and external MCP agents

Microgifter inline agents and external MCP clients should use the same Creator Campaign tool catalog and canonical services.

The difference is the access channel:

```text
Inline agent
→ Existing authenticated Microgifter session
→ Native permission context

External agent harness
→ MCP client and connection
→ Owner scopes and grant
→ Native permission context
```

Neither channel receives broader authority than the authenticated user and merchant workspace already possess.

## 14. Required module design hooks

During the Creator Campaign build, each canonical service should provide:

- Stable command input object
- Stable result object
- Permission check
- Workspace scope check
- State-transition validation
- Dry-run or validation mode where practical
- Idempotency support
- Domain event emission
- Audit record
- Error code
- Action receipt reference hook

These hooks make later MCP integration thin and predictable.

## 15. Build phases

### Creator Campaign Phase 1 — Native foundation

Build:

- Campaign builder
- Campaign records
- Products
- Eligibility
- Applications
- Invitations
- Participants
- Agreements
- Native permissions
- Native audit events

MCP work:

- Finalize read and draft tool schemas
- No canonical MCP actions

### Creator Campaign Phase 2 — Deliverables and content

Build:

- Deliverables
- Assignments
- Submissions
- Revisions
- Content review
- Publication proof

MCP work:

- Read tools
- Submission review simulations
- Draft feedback tools

### Creator Campaign Phase 3 — Tracking and CRM

Build:

- Tracking links
- Referral codes
- QR codes
- Event ingestion
- Attribution
- CRM activity

MCP work:

- Read tracking and attribution tools
- Campaign-health simulations
- No attribution override until native conflict handling is validated

### Creator Campaign Phase 4 — Compensation and finance

Build:

- Compensation rules
- Budget ledger
- Earnings
- Holds and reversals
- Manual payouts
- Disputes

MCP work:

- Read-only financial tools
- Earnings-review simulations
- Financial actions remain approval-gated

### Creator Campaign Phase 5 — Canonical MCP integration

Enable:

- Approved campaign actions
- Approved participant actions
- Approved content-review actions
- Approved attribution actions
- Approved financial actions
- Action receipts
- Automation definitions
- Bounded scheduled summaries and proposals

## 16. Initial MCP release boundary

Include:

- Read-only campaign tools
- Campaign draft creation and updates
- Campaign validation
- Draft creator invitations
- Draft merchant messages
- Application review summaries
- Content review summaries and draft feedback
- Campaign health summaries
- Earnings review summaries
- Approval-gated canonical actions after module validation
- Owner-controlled grants and fixed playbooks
- Complete receipts and audit evidence

Defer:

- Fully autonomous campaign publication
- Autonomous creator approval or removal
- Autonomous contract acceptance
- Autonomous attribution overrides
- Autonomous financial approval
- Autonomous payouts
- Arbitrary user-defined tools
- Unbounded playbooks
- Cross-merchant access
- Legacy Marketing Affiliate integration

## 17. Final recommendation

Build the Creator Campaign module first, but build it with the MCP contract in mind from the first service.

Do not wait until the module is complete to think about MCP inputs, scopes, receipts, and idempotency. Do wait until the native services are stable before allowing MCP tools to perform canonical actions.

The recommended pattern is:

```text
Design together
→ Build native module first
→ Integrate read and draft MCP tools during development
→ Add canonical MCP actions after native validation
→ Add bounded automation last
```
