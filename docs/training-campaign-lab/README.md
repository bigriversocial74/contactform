# Training Campaign Lab

This folder contains the build package for the Microgifter Training Campaign Lab on the `local-quest-workspace` branch.

The goal is to extend the Local Quest example into a new proof-of-action training module without changing the original quest script on `main`.

## Product statement

Microgifter Training Campaigns let organizations create action-based challenges, assign them to teams or public participants, collect proof of completion, verify progress, and issue rewards for completed sequences, streaks, and milestones.

## Core loop

```text
Organization creates campaign
Participant joins campaign
Participant completes task sequence
Participant uploads photo/video proof
Reviewer approves proof
System creates Action Receipt
Reward rules evaluate
Microgifter reward is issued
Wallet and claim status are tracked
Consistency/streak data updates
```

## Product and scope docs

- [Product Requirements](./product-requirements.md)
- [Expanded Platform Outline](./expanded-platform-outline.md)
- [MVP Scope](./mvp-scope.md)
- [Demo Script](./demo-script.md)
- [Acceptance Checklist](./acceptance-checklist.md)

## Build-control docs

- [Documentation Completion Plan](./documentation-completion-plan.md)
- [Branch Strategy](./branch-strategy.md)
- [Build Plan](./build-plan.md)
- [Route Map](./route-map.md)
- [Implementation Tickets](./implementation-tickets.md)
- [QA Test Script](./qa-test-script.md)
- [Agent Build Handoff](./agent-build-handoff.md)
- [Next Build Outline](./next-build-outline.md)

## Data and security docs

- [Schema Outline](./schema.md)
- [Schema Install Plan](./schema-install.md)
- [Status Model](./status-model.md)
- [UI Data Map](./ui-data-map.md)
- [Data Lifecycle](./data-lifecycle.md)
- [Security and Permissions](./security-permissions.md)

## Workflow docs

- [Admin Workflows](./admin-workflows.md)
- [Participant Workflows](./participant-workflows.md)
- [Open Questions](./open-questions.md)

## UI planning docs

- [UI Page Map](./ui/ui-page-map.md)
- [UI Layout Specification](./ui/ui-layout-spec.md)
- [Component Inventory](./ui/component-inventory.md)
- [Responsive Rules](./ui/responsive-rules.md)
- [Mockup Index](./ui/mockups.md)
- [Mockup Image Folder](./ui/mockups/)

## Build artifacts

```text
examples/local-quest-rewards/training-lab.php
examples/local-quest-rewards/training-campaigns.php
examples/local-quest-rewards/training-campaign-data.php
examples/local-quest-rewards/assets/training-lab.css
examples/local-quest-rewards/assets/training-lab.js
examples/local-quest-rewards/database/training_campaign_lab.sql
examples/local-quest-rewards/database/training_campaign_lab_seed.sql
scripts/validate_training_campaign_lab.php
```

## First MVP target

Build one complete vertical slice:

```text
Participant joins 5-Day Movement Challenge
Participant uploads proof for each required task
Admin approves each submission
Sequence becomes verified complete
Action Receipt is created
Reward rule becomes eligible
Microgifter reward is issued
Wallet displays reward status
```

## Recommended next build files

```text
examples/local-quest-rewards/training-storage.php
examples/local-quest-rewards/training-campaign-detail.php
examples/local-quest-rewards/training-sequence.php
examples/local-quest-rewards/training-proof-upload.php
examples/local-quest-rewards/admin-training-review.php
examples/local-quest-rewards/admin-training-receipts.php
examples/local-quest-rewards/training-rewards.php
examples/local-quest-rewards/training-profile-wallet.php
examples/local-quest-rewards/training-receipt-service.php
examples/local-quest-rewards/training-reward-service.php
```

## Build principle

Start with manual proof upload and manual review. Add AI-assisted review, computer vision, motion scoring, and agentic coaching after the basic proof/reward loop works.

## Validation

Run after each implementation phase:

```bash
php scripts/validate_training_campaign_lab.php
```

## MVP rule

Build one working vertical slice before adding advanced features:

```text
one campaign
one participant
four proof submissions
one reviewer
one verified sequence
one Action Receipt
one reward issue
```
