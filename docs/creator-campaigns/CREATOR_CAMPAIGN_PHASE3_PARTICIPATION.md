# Creator Campaign Phase 3 — Participation

Phase 3 adds the human-reviewed participation layer to the native Creator Campaign domain introduced in Phases 1 and 2.

## Delivered

### Creator experience

- Discover scheduled and active campaigns that accept public applications.
- Filter opportunities by search, category, and objective.
- Read campaign products, eligibility rules, application questions, dates, and merchant context.
- Save an application draft, submit it for human merchant review, resubmit requested information, or withdraw.
- View and respond to invitations addressed to the authenticated Creator account.
- View owned participant records and the agreement-pending handoff.

### Merchant experience

- Participation dashboard with campaign, application, invitation, participant, and agreement-pending totals.
- Review application answers and Creator profile snapshots.
- Explicitly start review, request information, approve, or decline.
- Search the approved Creator directory and invite an existing Creator account.
- Cancel pending invitations.
- View, suspend, restore, or remove participants.
- Inspect the append-only participation activity timeline.

## Human approval boundary

Application submission never creates an approved participant. The server records every submission as `submitted`, even when the legacy Phase 2 `automatic_acceptance` field was previously enabled. The Phase 3 migration resets that field to `0`.

Only an explicit authenticated merchant review action may approve an application. Invitation acceptance is permitted because the merchant previously selected and invited that specific Creator account.

Both approval paths create or restore exactly one campaign/Creator participant record in `agreement_pending`. Phase 3 cannot activate a participant.

## Phase 4 boundary

Agreement versions, acceptance receipts, rights, disclosures, and activation remain deferred to Phase 4. The participation service checks for the future agreement table but never creates agreement data. Participants remain `agreement_pending` until an immutable agreement is accepted through the approved Phase 4 workflow.

## Integrity controls

- Workspace-scoped merchant authorization.
- Active approved Creator-model requirement.
- Creator ownership checks on applications, invitations, and participant reads.
- One application, one invitation, and one participant per campaign/Creator pair.
- Optimistic locking on mutable participation records.
- Campaign-row locks on approval and invitation acceptance to enforce creator limits atomically.
- Live campaign status and deadline rechecks inside write transactions.
- Idempotent invitation creation and append-only participation events.
- Invitation expiration before listing or response.
- No reuse of the legacy rewards/CRM `campaigns` table.

## Schema

Migration:

`database/20260721_creator_campaign_participation_v3.sql`

Tables:

- `creator_campaign_applications`
- `creator_campaign_application_answers`
- `creator_campaign_invitations`
- `creator_campaign_participants`
- `creator_campaign_participation_events`

The migration also adds scoped merchant and Creator permissions. It does not add agreements, deliverables, tracking, compensation, earnings, payouts, messaging, disputes, or MCP execution tables.

## Routes

Creator:

- `/creator-campaigns.php`
- `/api/creator/campaigns.php`

Merchant:

- `/merchant-creator-participation.php`
- `/api/merchant/creator-campaign-participation.php`

Existing merchant campaign cards link to the separate participation workspace.

## Validation

- PHP 8.2 and 8.3 syntax and contract validation.
- Creator and merchant JavaScript syntax checks.
- PHPUnit architectural contract.
- Canonical migration-manifest validation.
- MySQL 8 migration and lifecycle test covering application submission, human approval, invitation acceptance, capacity, ownership, and the agreement-pending boundary.

## Deployment order

1. Human review and merge of the Phase 3 PR.
2. Deploy the merged integration branch.
3. Human-approved import of the Phase 3 SQL migration.
4. Verify Creator and merchant participation workspaces.
5. Keep participant activation disabled until Phase 4 is separately approved, merged, deployed, and imported.
