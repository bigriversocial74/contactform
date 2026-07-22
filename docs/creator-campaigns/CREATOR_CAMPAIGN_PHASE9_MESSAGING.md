# Creator Campaign Phase 9 — Messaging and Notifications

Phase 9 integrates Creator Campaign communication with Microgifter's existing Messages and Notifications systems. It does not create a parallel inbox, message-body store, preference engine, or delivery dispatcher.

## Canonical systems reused

- `message_threads`
- `message_thread_participants`
- `messages`
- `message_thread_settings`
- `notifications`
- `notification_preferences`
- `notification_delivery_jobs`
- existing Messages center, email/SMS/push delivery, quiet hours, digests, mute, pin, archive, moderation, audit, and delivery validation

## Phase 9 bridge records

- `creator_campaign_message_contexts` — one canonical thread per campaign participant.
- `creator_campaign_message_links` — links canonical messages to campaign, deliverable, submission, earning, payout, or dispute context without copying message bodies.
- `creator_campaign_internal_notes` — merchant-only operational notes that never enter Creator-visible threads.

## Delivered

- Merchant and Creator campaign-message workspaces.
- Canonical thread creation and participant membership.
- Idempotent message sends.
- Existing asset-reference reuse without duplicate upload storage.
- Creator Campaign source labels and deep links inside the existing Messages center.
- Preference-aware in-app, email, SMS, and push delivery jobs.
- Thread mute and quiet-hour enforcement through existing services.
- System-event messages for later services to publish authoritative lifecycle updates.
- Optimistic merchant close/reopen controls.
- Workspace-scoped merchant authorization and Creator ownership checks.
- Internal notes protected by a separate permission and table.

## Boundaries

Phase 9 does not send marketing broadcasts, create CRM contacts, execute payouts, change financial records, replace the existing Messages center, store attachment bytes, or expose MCP execution.

## SQL

`database/20260722_creator_campaign_messaging_notifications_v9_single_install.sql`
