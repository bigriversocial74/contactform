# Creator Campaign Phase 14 — Production Pilot & Operator Experience

## Purpose

Phase 14 turns the Creator Campaign MCP foundation from Phases 13A–13D into a merchant-operated production pilot. It adds one cockpit for setup, readiness, bounded-playbook monitoring, review artifacts, recovery evidence, emergency shutdown, and deliberate handoff into the Phase 13C owner-approval flow.

Phase 14 does **not** add a new execution engine. Canonical campaign data, grants, definitions, runs, actions, receipts, drafts, approvals, and security evidence remain in the existing Microgifter tables and services.

## Operator cockpit

`/account-creator-campaign-pilot.php`

The cockpit provides:

- guided readiness checks derived from live MCP and Creator Campaign records
- merchant launch checklist and named escalation contact
- pilot lifecycle: setup, ready, active, paused, completed, disabled
- six bounded-playbook status cards
- connection, grant, definition, trigger, run, artifact, action-grant, and security health warnings
- run history with structured recovery evidence
- recent Agent Drafts review artifacts
- approved-artifact handoff into a new Phase 13C request
- durable operator and security event feeds
- workspace emergency stop

## Tables

Phase 14 adds three additive operator tables:

- `creator_campaign_operator_pilots`
- `creator_campaign_operator_events`
- `creator_campaign_operator_handoffs`

These tables store operator state and evidence only. They do not duplicate campaign state, MCP authority, automation runs, drafts, action approvals, or receipts.

## Readiness model

The cockpit calculates eight readiness checks:

1. active draft-authority merchant connection
2. required Phase 13D playbook scopes
3. active bounded-playbook grant
4. active matching definition and manual trigger
5. deployment attestation
6. SQL attestation
7. emergency-stop training attestation
8. named pilot support contact

Starting or resuming the pilot requires the mandatory technical and owner-attested controls. Completing the pilot additionally requires validated bounded-run evidence and owner review evidence.

## Emergency stop

The workspace emergency stop is a real fail-closed control.

When activated, it:

- blocks all new Phase 13D bounded-playbook runs for the workspace
- cancellation-requests active automation runs
- pauses manual bounded-playbook triggers
- pauses Phase 13D definitions
- pauses parent grants used by those definitions
- increments grant revocation versions
- records operator, audit, event, and security evidence

Clearing the stop only removes the workspace-level block. It leaves grants, definitions, and triggers paused. The merchant must review and resume each authority record manually.

## Run recovery

Phase 14 never retries a failed external-agent run automatically. Operators may record one of these recovery dispositions:

- retry externally with a new idempotency key
- review configuration and authority
- pause the definition
- do not retry
- resolved after review

The decision and note are stored as operator evidence. Pausing a definition also pauses its manual trigger.

## Recommendation handoff

An approved Phase 13D artifact can seed a supported Phase 13C action request.

The handoff:

1. verifies the pilot is active and emergency stop is clear
2. verifies the artifact belongs to the merchant workspace and is approved
3. verifies the selected action is permitted for that playbook
4. verifies the approval-gated grant, tool, scope, connection, client, workspace, risk ceiling, and campaign target
5. seeds canonical IDs and recommendation data from the structured artifact
6. requires the operator to review the exact action input
7. calls the existing Phase 13C request service
8. creates a `waiting_for_approval` action only

The handoff does not approve or execute the action. The merchant must separately approve it in `/account-creator-campaign-actions.php`, then use the second explicit execution control. Native execution revalidates current state and authority again.

## Supported handoffs

- Campaign preparation → publish or schedule
- Application review → approve or decline application
- Content review → approve, request revision, or reject submission
- Campaign health → pause, resume, complete, or cancel campaign
- Earnings review → approve, hold, reject, or reverse earning
- Creator outreach → send invitation

Campaign-health recommendations and operator choices are advisory. The server still performs all Phase 13C validation before accepting the request.

## Security and authority boundary

Phase 14 cannot:

- schedule or autonomously trigger a playbook
- approve a review artifact automatically
- approve or execute a canonical action automatically
- mutate a campaign directly
- decide an application or submission directly
- send invitations or messages directly
- change attribution, earnings, payouts, agreements, or disputes directly
- call a payment provider or move money
- reactivate authority after an emergency stop

## Production smoke test

1. Import the Phase 14 SQL.
2. Deploy the integration branch.
3. Open the pilot cockpit as a merchant owner.
4. Save the support contact and checklist attestations.
5. Confirm one active draft connection, playbook grant, definition, and manual trigger.
6. Start the pilot.
7. Run Campaign Health through the authorized external client.
8. Confirm one successful run, one action, one canonical receipt, and one pending Agent Drafts artifact.
9. Approve the artifact.
10. Prepare a supported Phase 13C request from the cockpit.
11. Confirm the action appears as `waiting_for_approval` and no canonical effect occurred.
12. Reject it or separately approve and execute it through the existing two-step owner flow.
13. Activate the emergency stop and confirm new playbook runs fail closed.
14. Clear the stop and confirm grants/definitions remain paused until manually resumed.

## Rollback

Code rollback:

- deploy the prior integration commit
- Phase 13D continues operating unless a Phase 14 emergency stop remains active

Operational rollback:

- activate the emergency stop before code rollback
- leave grants and definitions paused
- investigate operator and security events

Database rollback is normally unnecessary because the migration is additive. The three operator tables can remain unused without affecting prior phases.
