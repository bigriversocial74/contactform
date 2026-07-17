# Merchant Contact Action Center v1

## Purpose

Merchant Contact Action Center v1 turns an exact Merchant CRM contact selection into a persistent working context inside `/merchant-agent-chat.php`.

The selected contact is scoped to one Merchant Agent thread. A merchant can continue asking follow-up questions without repeating `@username`, switch to another exact contact, clear the contact, load another chat with its own contact selection, or delete the thread and its contact-selection events.

## Selection and persistence

- CRM result cards keep the existing **Select** behavior and also activate the Contact Action Center.
- Exact `@username` prompts replace the previous selected contact.
- Selection state is stored in existing `campaign_events` records:
  - `merchant.agent_chat.contact_selected`
  - `merchant.agent_chat.contact_cleared`
- Thread matching uses validated JSON and an exact `thread_public_id` comparison.
- Clearing a chat clears its selected contact.
- Deleting a chat deletes its user, assistant, selected-contact, and cleared-contact events.
- No new table or migration is required.

## Contact contract

The browser receives one versioned `contact_action_center` object containing:

- public CRM contact ID and `@mention`
- display name
- lifecycle stage and CRM status
- engagement score and next-best action
- purchase value
- reward issued, claimed, and redeemed totals
- campaign count and recent campaign history
- recent CRM activity
- message, note, and follow-up counts
- recent messages, notes, and follow-up tasks
- profile, CRM, timeline, and follow-up links
- server-declared capabilities and quick actions

The contract does not expose email addresses, phone numbers, private database IDs, or unrelated contacts.

## Merchant Agent context

Claude receives a sanitized selected-contact snapshot containing only merchant-authorized information needed for the current request. Public and internal identifiers, action URLs, email, and phone are removed from the prompt context.

The Agent may use:

- purchases and purchase value
- rewards, claims, and redemptions
- campaign history and engagement
- recent CRM events
- message previews
- CRM notes
- follow-up tasks
- lifecycle, status, score, tags, and next-best action

The exact CRM contact public ID is added server-side to review payloads after the model response so approved workflows can target the correct customer without exposing the identifier to the model.

## Quick actions

The Contact Action Center provides:

1. Summarize activity
2. Draft follow-up
3. Recommend reward
4. Draft campaign invite
5. Create follow-up task

Quick actions submit through the existing Merchant Agent chat runtime so normal message rendering, model routing, usage logging, policy checks, and review bridging remain authoritative.

## Approval boundary

- Summaries remain advisory.
- Message and campaign-invitation outputs are drafts.
- Reward outputs are recommendations.
- Follow-up tasks are review-ready proposals.
- Review-mode outputs route through the existing Agent Review queue.
- The Contact Action Center cannot directly send a message, issue a reward, launch a campaign, or create a task.
- Existing server endpoints remain final authority for ownership, permissions, CSRF, validation, idempotency, and execution.

## Permissions and ownership

- Merchant Agent access requires the existing package entitlement and AI permissions.
- Contact selection and context require `merchant.campaigns.view`.
- Contact action generation requires `merchant.ai.plan`.
- Review-queue and message-draft autonomy controls remain enforced.
- Canonical contact reads are scoped to the authorized merchant workspace owner.
- Chat thread selection events remain scoped to the authenticated Merchant Agent actor.

## SQL

No SQL required. The feature reuses `campaign_events`, `merchant_agent_threads`, `merchant_crm_contacts`, `merchant_crm_contact_events`, `merchant_crm_contact_campaigns`, `campaign_contacts`, `merchant_crm_notes`, `message_threads`, and `messages`.
