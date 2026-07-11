# Loyalty Quest Admin Operations and Moderation v1

## Purpose

The Loyalty Quest Command Center gives authorized Microgifter operators a cross-merchant view of campaign health, merchant-review backlog, delivery failures, Inbox reward outcomes, and recent admin actions.

It uses the existing campaign, evidence, notification-delivery, operational-alert, campaign-event, and audit authorities. It does not create a second moderation queue, reward engine, claim system, or ownership lifecycle.

## Admin page

Open:

`/admin/loyalty-quests.php`

Read access uses the existing `admin.operations_command.view` permission. Campaign-control, review-reminder, and delivery-retry writes require `admin.operations_command.manage`. Super administrators retain access through the existing permission resolver.

## Allowed operations

Authorized operators may:

- pause an active or scheduled Loyalty Quest
- resume a paused Loyalty Quest when it has not ended
- end an active, scheduled, or paused Loyalty Quest
- create an operational reminder for a merchant with submitted evidence waiting for review
- retry a failed, dead-letter, or processing job stuck longer than fifteen minutes

Every write requires CSRF validation and an operator reason between 12 and 1000 characters. The operation is written to the campaign event ledger and the platform audit log.

## Authority boundary

This admin console does not approve or reject participant evidence. It also does not:

- issue or cancel participant rewards
- reveal participant proof notes or proof URLs
- expose raw location coordinates
- reveal full recipient email addresses in delivery recovery
- mutate claim ownership
- redeem a PPPM item
- create a second Wallet or Inbox lifecycle

Merchant evidence decisions stay in the merchant Loyalty Quest review workflow. Reward ownership stays Microgift → PPPM → Inbox. Claim and redemption authority stays in the existing merchant scanner and PPPM operations.

## Campaign controls

Campaign status actions use row locks and validated transitions:

- `active` or `scheduled` → `paused`
- `paused` → `scheduled` when the start date is still in the future
- `paused` → `active` when the start date has arrived
- `active`, `scheduled`, or `paused` → `completed`

Resuming an already-ended campaign is rejected. Ending a campaign does not alter previously issued Microgifts, PPPM ownership, or redemption history.

## Review backlog

The evidence queue contains only operational metadata:

- evidence public identifier
- evidence type
- campaign and merchant identity
- participation identifier
- submission age

It excludes participant names, email addresses, proof content, proof URLs, claim codes, signed verification payloads, and precise coordinates.

A merchant-review reminder has a twelve-hour cooldown. The reminder creates a campaign event, an audit record, and an operational alert when that schema is available. It never changes evidence status.

## Delivery recovery

Delivery recovery includes failed, dead-letter, and stale processing Loyalty Quest jobs. Recipient email addresses are masked. Administrative retry:

- reuses the canonical `message_delivery_jobs` record
- retains prior attempt evidence
- enforces a maximum of ten attempts
- returns the job to the shared queue
- records the operator reason and campaign event

## Database and deployment

No new SQL migration is required. This section uses existing tables:

- `campaigns`
- `campaign_events`
- `loyalty_quest_participations`
- `loyalty_quest_evidence`
- `wallet_items`
- `message_events`
- `message_delivery_jobs`
- `operational_alerts`
- platform audit tables

Delivery recovery reports as unavailable until the registered Loyalty Quest notification migration has been applied.

## Verification

The dedicated workflow validates:

- PHP 8.2 and PHP 8.3 syntax
- JavaScript syntax
- the static 10/10 authority contract
- strict MySQL behavior for merchant aggregation, PII masking, evidence backlog, delivery recovery, and summary totals
- admin permission and navigation integration
- Loyalty Quest campaign, participant, merchant, notification, analytics, media, and migration regressions

Browser rendering, real administrator role assignments, production operational-alert delivery, and production-volume query performance require environment-specific verification after deployment.
