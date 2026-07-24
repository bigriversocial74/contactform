# Investor Due Diligence, Data Room & Communications v3

## Purpose

Phase 3 adds the secure diligence and communications layer on top of the merged Investor Wizard v1 and Investor Pipeline v2 foundations.

It provides:

- Governed, round-scoped data-room folders and document references.
- Immutable document versions.
- Investor-submitted questions, document requests, and clarification requests.
- Draft, internal-review, legal-review, approved, and published response states.
- Reusable published investor Q&A.
- Investor meeting records, outcomes, sentiment, and next steps.
- Portal-published investor communications and recipient-view tracking.
- Non-binding investor interest submissions.
- Deterministic investor engagement snapshots.
- Draft-only Claude diligence and communications assistance.

This module does not process investment funds, issue securities, verify accreditation, electronically sign documents, replace the legal stock ledger, or provide legal approval.

## Installation

Import in this order:

1. `database/20260723_investor_role_investment_wizard_v1_single_install.sql`
2. `database/20260723_investor_pipeline_portal_publishing_v2_single_install.sql`
3. `database/20260723_investor_diligence_communications_v3_single_install.sql`

Phase 1 and Phase 2 SQL must already be installed. The Phase 3 migration is additive and does not drop or truncate prior tables.

## Main pages

- `/admin/investor-diligence.php`
- `/admin/investor-pipeline.php`
- `/admin/investment-wizard.php`
- `/investor-portal.php`

## Admin workflow

1. Create and approve an official round in the Investment Wizard.
2. Publish the round through Investor Pipeline portal controls.
3. Open Investor Due Diligence.
4. Select the official round.
5. Create data-room folders.
6. Add controlled external document references.
7. Keep documents in draft, internal review, or legal review until approved.
8. Publish only approved document versions.
9. Review investor-submitted diligence requests.
10. Save response drafts and advance them through review.
11. Publish the approved response explicitly.
12. Convert reusable answers into the Q&A Library when appropriate.
13. Record meetings and outcomes.
14. Publish approved investor communications.
15. Refresh engagement snapshots.

## Data room

Documents are controlled records pointing to approved `https://` or `http://` locations. The application does not create unrestricted public upload paths.

Each record includes:

- Round
- Folder
- Classification
- Visibility
- Review status
- Legal-review requirement
- External URL
- Download control
- Expiration date
- Investor-facing description
- Internal description
- Current version
- Immutable version history

Visibility levels:

- Approved investors
- Selected investors
- Funded investors

A document must be in `published` status and unexpired before appearing in the Investor Portal.

## Diligence requests

Investor-facing request types:

- Question
- Document request
- Clarification

Internal workflow:

- Submitted
- Acknowledged
- Assigned
- Researching
- Draft Response
- Internal Review
- Legal Review
- Answered
- Closed
- Declined

Only `approved_response` is returned to the investor. Internal notes and non-published response versions remain private.

## Q&A Library

Published Q&A can be general or round-specific. Every edit increments the version number. Publishing requires the `admin.investment.diligence.publish` permission.

## Meetings

Meeting records support:

- Intro
- Follow-up
- Demo
- Diligence
- Terms
- Closing
- Other

The system records meeting details and provides meeting links, but it does not silently create external calendar events.

## Communications

Portal communications support these audiences:

- Approved investors
- Selected investors
- Funded investors

The system creates portal recipient records when a communication is published. It does not automatically send email.

## Non-binding interest

The Investor Portal requires an explicit acknowledgement that an interest submission is non-binding. Submissions do not change signed or funded totals. An administrator must separately update the canonical investor-round relationship in Investor Pipeline.

## Engagement score

The deterministic 100-point score is composed of:

- Portal sessions: up to 15 points
- Round views: up to 10 points
- Document views: up to 20 points
- Unique documents: up to 10 points
- Metric views: up to 8 points
- Diligence questions: up to 12 points
- Communications viewed: up to 10 points
- Completed meetings: up to 10 points
- Recency: up to 15 points

An investor is marked stalled when no recorded engagement exists or the last engagement is more than 30 days old. This is an operational indicator, not an investment recommendation.

## Claude configuration

Phase 3 reuses `includes/ai/anthropic-client.php` and the canonical Microgifter AI model catalog.

Claude actions are explicit and draft-only. Claude cannot:

- Publish a response or communication
- Grant data-room access
- Send email
- Change official terms
- Record commitments
- Change signed or funded totals
- Verify accreditation
- Provide legal approval

## Validation

Run:

```bash
php scripts/validate_investor_diligence_communications_v3.php
node --check assets/js/investor-diligence-v3.js
node --check assets/js/investor-portal-v3.js
```

The dedicated GitHub workflow also checks PHP 8.2 and PHP 8.3 syntax, JavaScript syntax, the Phase 3 source contract, SQL table presence, and additive migration safety.
