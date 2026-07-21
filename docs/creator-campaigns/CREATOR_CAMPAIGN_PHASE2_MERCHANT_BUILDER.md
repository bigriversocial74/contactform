# Creator Campaign Phase 2 — Merchant Campaign Builder

## Status

Implementation scope for the second Creator Campaign delivery phase. Phase 1 Native Foundation is already merged and its SQL has been imported.

## Approved references

This phase follows the repository planning chain:

1. `README.md`
2. `CREATOR_CAMPAIGN_SYSTEM_SPECIFICATION.md`
3. `CREATOR_CAMPAIGN_DATABASE_API_OUTLINE.md`
4. `CREATOR_CAMPAIGN_UI_MOCKUPS.md`
5. `CREATOR_CAMPAIGN_UI_PROMPTS.md`
6. `CREATOR_CAMPAIGN_MCP_INTEGRATION_AMENDMENT.md`

## Delivered merchant surfaces

- `/merchant-creator-campaigns.php`
- `/merchant-creator-campaign-builder.php`
- `/api/merchant/creator-campaigns.php`

The existing `/merchant-campaigns.php` reward/CRM campaign module remains unchanged.

## Functional scope

### Campaign workspace

- Workspace-scoped campaign list, status filtering, search, metrics, pagination, and builder-readiness scores.
- Separate navigation entry under Products & Engagement.
- Create, inspect, edit, validate, duplicate, cancel, pause, resume, complete, and archive service paths.

### Ten-step builder

The approved ten-step information architecture is rendered in full.

- Step 1 — Campaign Details: writable.
- Step 2 — Products and Offers: writable.
- Step 3 — Creator Eligibility and Application Questions: writable.
- Steps 4–9: visible and dependency-gated to their approved implementation phases.
- Step 10 — Review and Readiness: operational.

No generic JSON placeholders are used for deliverables, compensation, attribution, budgets, content rights, or contractual terms.

### Data additions

The migration adds typed builder progress and campaign configuration fields to `creator_campaigns` and creates `creator_campaign_application_questions`.

The migration deliberately does not add compensation, payout, tracking, agreement, or deliverable tables.

## Security and integrity

- Existing active-user, Merchant-model, package, platform-permission, workspace-role, and object-scope checks remain authoritative.
- All browser writes require CSRF validation.
- Products, product versions, rewards, assets, and managers are resolved within the active workspace.
- Step saves atomically replace child rows inside a service-owned transaction.
- Every write requires the current optimistic `lock_version`.
- Duplicate requests use idempotency keys.
- Automatic acceptance fails closed until Creator Participation is installed.
- Scheduling and activation remain blocked until immutable Agreement Version 1 is available.

## Validation score

The phase validator assigns 20 points to each category:

- Schema and domain boundaries
- Services and authorization
- Merchant workflow and UX
- Lifecycle and concurrency
- Validation and delivery

A merge-ready implementation must score 100/100 and pass PHP 8.2, PHP 8.3, JavaScript syntax, PHPUnit contracts, the canonical migration manifest, a complete MySQL 8 migration, and the clean-database builder lifecycle.

## SQL

`database/20260721_creator_campaign_merchant_builder_v2.sql`
