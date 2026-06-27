# Message Delivery Verification

## Goal

Prove Merchant CRM message delivery in three customer-visible places:

1. Merchant CRM send confirmation.
2. Customer notifications.
3. Customer Messages thread.

## Built

- `/notifications.php` now loads notification source metadata decoration.
- `assets/js/notifications-source-metadata.js` decorates notifications with source label, source channel, thread ID, and message ID when available.
- `assets/js/merchant-crm-messages.js` shows a delivery proof block after direct CRM message sends.
- `api/messages/thread.php` recognizes Merchant CRM threads and returns source context.
- `assets/js/messages-center.js` renders delivery context inside the customer message thread detail panel.
- `scripts/smoke_crm_message_delivery.php` creates a real database smoke path for merchant, customer, CRM contact, message thread, participants, message, and notification.
- `scripts/validate_message_delivery_verification.php` provides static PR validation markers.

## Expected UX

When a merchant sends a direct CRM message:

- The CRM modal stays open and displays delivery proof.
- If the contact has an account, the proof says `Delivered to customer Messages`.
- Proof chips show thread, message, notification, and customer user IDs.
- The customer notification shows `Merchant CRM` metadata and opens directly to the message thread.
- The customer Messages thread shows the source badge and context panel with merchant, campaign, campaign type, contact source, and CRM contact ID.

## Smoke command

Run after migrations on a safe test database:

```bash
php scripts/smoke_crm_message_delivery.php
```

The script writes a JSON result with the created merchant user, customer user, contact, thread, message, notification, and pass/fail checks.
