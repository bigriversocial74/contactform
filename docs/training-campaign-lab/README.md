# Training Campaign Lab

This folder contains the **documentation-only planning package** for the Microgifter Training Campaign Lab on the `local-quest-workspace` branch.

The goal is to document a future proof-of-action training module before implementation begins.

## Current status

```text
Documentation only.
Do not build code yet.
Do not add PHP implementation files yet.
Do not add SQL implementation files yet.
Do not modify existing Loyalty Quest files.
```

The previous premature implementation files have been removed from this branch. The remaining work in this folder should be planning, specifications, routes, workflows, schema design, QA plans, and agent handoff instructions only.

## Product statement

Microgifter Training Campaigns will let organizations create action-based challenges, assign them to teams or public participants, collect proof of completion, verify progress, and issue rewards for completed sequences, streaks, and milestones.

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
- [Pricing Concept](./pricing-concept.md)
- [Account System and Hosting Integration](./account-system-integration.md)
- [Demo Script](./demo-script.md)
- [Acceptance Checklist](./acceptance-checklist.md)

## Build-control and guardrail docs

- [Do Not Build Yet](./DO-NOT-BUILD-YET.md)
- [Documentation Completion Plan](./documentation-completion-plan.md)
- [Branch Strategy](./branch-strategy.md)
- [Build Plan](./build-plan.md)
- [Build Readiness Checklist](./build-readiness-checklist.md)
- [Stage 1 Build Start Checklist](./stage-1-build-start-checklist.md)
- [Implementation Boundaries](./implementation-boundaries.md)
- [Future File Manifest](./future-file-manifest.md)
- [Route Map](./route-map.md)
- [Route/Data Contract](./route-data-contract.md)
- [Implementation Tickets](./implementation-tickets.md)
- [QA Test Script](./qa-test-script.md)
- [Agent Build Handoff](./agent-build-handoff.md)
- [Next Build Outline](./next-build-outline.md)
- [Final Docs Audit](./final-docs-audit.md)

## Data and security docs

- [Schema Outline](./schema.md)
- [Schema Install Plan](./schema-install.md)
- [SQL Schema Design](./sql-schema-design.md)
- [Status Model](./status-model.md)
- [UI Data Map](./ui-data-map.md)
- [Data Lifecycle](./data-lifecycle.md)
- [Security and Permissions](./security-permissions.md)
- [Security Decision Log](./security-decision-log.md)

## Workflow docs

- [Admin Workflows](./admin-workflows.md)
- [Participant Workflows](./participant-workflows.md)
- [Open Questions](./open-questions.md)

## UI planning docs

- [UI Page Map](./ui/ui-page-map.md)
- [UI Layout Specification](./ui/ui-layout-spec.md)
- [Admin Backend UI Patterns](./admin-backend-ui-patterns.md)
- [Component Inventory](./ui/component-inventory.md)
- [Responsive Rules](./ui/responsive-rules.md)
- [Mockup Index](./ui/mockups.md)
- [Mockup Image Folder](./ui/mockups/)

## Future MVP target

The future build should create one complete vertical slice:

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

## Future build principle

Start with manual proof upload and manual review. Add AI-assisted review, computer vision, motion scoring, and agentic coaching only after the basic proof/reward loop works.

## Future MVP rule

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

## Stop rule

If an agent is working from this folder and the user has not explicitly said `build code`, the agent must stop at documentation and planning. Do not create PHP, SQL, CSS, JS, validation scripts, upload folders, or runtime files until implementation is explicitly approved.
