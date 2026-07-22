# Creator Campaign Phase 4 — Deliverables, Submissions, and Content Review

Phase 4 adds the operational content-production layer on top of the merged Creator Campaign participation and immutable-agreement foundation.

## Delivered

- Campaign-owned deliverable definitions with type, platform, format, quantity, instructions, talking points, disclosures, publication requirements, revision limits, and due dates.
- Participant deliverable assignments governed by the participant's accepted immutable agreement version.
- Idempotent assignment synchronization for active participants.
- Creator draft, submit, revise, withdraw, and publication-proof workflows.
- One canonical submission per participant deliverable with append-only immutable revision snapshots.
- External content and proof assets with URL validation and content hashes.
- Merchant review actions for under review, revision requested, approved, rejected, and verified states.
- Revision-limit enforcement, optimistic locking, workspace ownership checks, creator ownership checks, and CSRF protection.
- Canonical Microgifter notifications for assignments, submissions, revisions, approvals, rejections, and publication verification.
- Dedicated merchant and Creator workspaces.

## Routes

Merchant:

- `/merchant-creator-deliverables.php`
- `/api/merchant/creator-campaign-deliverables.php`

Creator:

- `/creator-campaign-deliverables.php`
- `/api/creator/campaign-deliverables.php`

## Schema

Migration: `database/20260721_creator_campaign_deliverables_v4.sql`

Tables:

- `creator_campaign_deliverables`
- `creator_campaign_participant_deliverables`
- `creator_campaign_submissions`
- `creator_campaign_submission_revisions`
- `creator_campaign_assets`

## Integrity rules

1. Deliverables belong to one Creator Campaign.
2. Assignments belong to one active participant and one deliverable.
3. Every assignment references the accepted agreement version that governs it.
4. One assignment exists per participant and campaign deliverable.
5. One canonical submission exists per assignment.
6. Every creator save, submit, merchant decision, and publication-proof action appends an immutable revision snapshot.
7. Merchant review is workspace scoped and Creator actions are owner scoped.
8. Terminal verified, waived, and cancelled assignments cannot be edited.
9. Notification failure cannot invalidate the canonical content transaction.
10. Tracking, attribution, compensation, earnings, payouts, disputes, and MCP remain outside Phase 4.

## Deployment

1. Merge the approved Phase 4 PR into `integration-from-repair-20260628`.
2. Deploy the updated integration branch.
3. Import `database/20260721_creator_campaign_deliverables_v4.sql` once.
4. Open the merchant workspace and create active deliverable definitions.
5. Use **Assign Active Deliverables** to synchronize assignments for active accepted participants.
6. Verify the Creator submission, merchant review, revision, publication-proof, and verification flows.
