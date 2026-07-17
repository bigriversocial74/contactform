# Merchant Contact Action Center v1.1 — Follow-Up Workspace

## Purpose

Contact Action Center v1.1 turns the selected-contact summary from v1 into a daily CRM working surface while preserving merchant ownership, review-first execution, and the existing Agent Review queue.

## Included workspace sections

### Filtered timeline

The selected contact timeline can be filtered by:

- All activity
- Purchases
- Rewards, claims, and redemptions
- Messages
- Campaigns
- Tasks and notes

Filtering is performed in the browser against the merchant-scoped Contact Action Center response. The complete CRM timeline remains available through the existing customer timeline link.

### Internal notes

Merchants can add an internal CRM note without leaving Merchant Agent.

- Notes are written to the existing `merchant_crm_notes` table.
- The CRM contact is resolved by public ID and authorized merchant owner.
- The authenticated Merchant Agent actor is stored as the note author.
- A matching `crm.note.added` CRM event is recorded.
- Note text is never sent to Claude by this action.

### Follow-up task builder

The builder supports:

- Call
- Email
- Reward reminder
- Campaign invite
- Customer service
- Low, medium, or high priority
- Due date
- Editable objective

The builder does not create a task directly. It creates an idempotent `create_crm_followup_task` recommendation in the existing Agent Review queue.

### Editable message draft

The draft editor supports:

- Email
- SMS
- CRM message
- Social DM
- Subject or internal label
- Editable message body

The draft is not sent from Contact Action Center. It creates an idempotent `create_message_draft` recommendation in Agent Review.

### Review status

The selected contact panel displays recent Contact Action Center review items with:

- Draft type
- Channel or due date
- Waiting, approved, rejected, deferred, failed, or executed status
- Direct link to the matching Agent Review item

## Security and data boundaries

- All contact reads are scoped to the authorized merchant workspace owner.
- Selection and Agent plans remain scoped to the authenticated Merchant Agent actor.
- Writes require CSRF validation.
- Notes require `merchant.campaigns.manage`.
- Review drafts require `merchant.ai.plan`, `merchant.ai.review`, and `merchant.campaigns.view`.
- Message drafts also require the Merchant Agent message-draft autonomy capability.
- Review queue creation is subject to autonomy and administrative usage limits.
- Contact IDs are attached server-side after ownership validation.
- No email address, phone number, database ID, or unrelated contact is sent to Claude.
- No message is sent and no follow-up task is executed directly.

## Idempotency

Each task or message draft receives a client-generated idempotency key. The server checks existing Agent Review items before creating another plan item. Editing any draft field resets the key and allows a new intentional revision.

## Existing schema

No SQL migration is required. v1.1 uses:

- `merchant_crm_contacts`
- `merchant_crm_notes`
- `merchant_crm_contact_events`
- `campaign_contacts`
- `campaign_events`
- `ai_merchant_plans`
- `ai_merchant_plan_items`
- Existing Merchant Agent thread, permission, autonomy, audit, and review infrastructure

## Production QA checklist

1. Select a CRM contact from Merchant Agent search.
2. Confirm the workspace persists when switching away from and back to the same chat.
3. Filter the timeline through every category.
4. Add an internal note and confirm it appears in the panel and customer profile.
5. Prepare a follow-up task and confirm exactly one Agent Review item is created.
6. Double-click or retry the same task submission and confirm idempotency prevents duplication.
7. Prepare and edit a message draft for each channel.
8. Confirm no message is sent from the panel.
9. Approve, defer, and reject test items and confirm status updates in the selected contact panel.
10. Verify another merchant workspace cannot resolve or modify the contact.
