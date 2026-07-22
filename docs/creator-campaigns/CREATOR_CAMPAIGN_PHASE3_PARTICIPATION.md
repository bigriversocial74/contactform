# Creator Campaign Phase 3 — Participation, Agreements, and Active Workspace

Phase 3 completes the original creator-participation scope on top of the native Creator Campaign foundation and merchant builder.

## Delivered

- Creator discovery for scheduled and active campaigns.
- Draft, submit, resubmit, withdraw, and review application lifecycles.
- Optional rule-based automatic acceptance with fail-closed eligibility and capacity checks.
- Manual merchant review remains available for all submitted applications.
- Existing-account creator invitations with idempotent creation, expiration, acceptance, decline, and cancellation.
- One participant record per campaign/creator with optimistic locking and participant-capacity enforcement.
- Immutable agreement versions with SHA-256 content hashes, version history, acceptance/decline receipts, and reacceptance after material changes.
- Creator activation only after the current agreement version is accepted.
- Merchant applications, invitations, participants, agreements, creator-directory, and activity views.
- Creator discovery, applications, invitations, agreements, and active-campaign workspace.

## Application approval modes

When `automatic_acceptance` is disabled, submitted applications remain available for merchant review. When enabled, the server evaluates the live Creator profile against every required campaign eligibility rule and the participant limit. Passing applications are approved, create one `agreement_pending` participant, and receive Agreement Version 1. Missing values, failed required rules, inactive Creator accounts, closed campaigns, or exhausted participant capacity fail closed and leave the application submitted for merchant review.

Automatic acceptance never bypasses agreement acceptance. A creator becomes active only after accepting the current immutable agreement version.

## Agreement lifecycle

Approved applications and accepted invitations receive Agreement Version 1. Each version captures campaign identity, products, eligibility rules, application questions, participant identity, dates, deliverable summary, compensation summary, rights, disclosures, cancellation, reversal, and creator-specific terms. Accepted versions are immutable. Material changes create a new version and may return an active participant to `agreement_pending` until reaccepted.

## Schema

Migration: `database/20260721_creator_campaign_participation_v3.sql`

Tables:

- `creator_campaign_applications`
- `creator_campaign_application_answers`
- `creator_campaign_invitations`
- `creator_campaign_participants`
- `creator_campaign_participation_events`
- `creator_campaign_agreements`
- `creator_campaign_agreement_versions`
- `creator_campaign_agreement_acceptances`

## Boundaries

Phase 4 remains focused on deliverable assignments, content submissions, revisions, merchant content review, publication proof, verification, and notifications. Phase 3 does not create tracking, CRM attribution, earnings, payout, dispute, or MCP execution tables.

## Routes

Creator:

- `/creator-campaigns.php`
- `/api/creator/campaigns.php`

Merchant:

- `/merchant-creator-participation.php`
- `/api/merchant/creator-campaign-participation.php`

## Deployment

1. Review and merge the Phase 3 PR into `integration-from-repair-20260628`.
2. Deploy the updated integration branch.
3. Import `database/20260721_creator_campaign_participation_v3.sql` once.
4. Verify merchant and Creator participation workspaces.
