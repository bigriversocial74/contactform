# Merchant-to-Customer Messaging Audit

## Problem observed

From Merchant CRM, sending a direct message did not provide a clear success confirmation. When logging into the intended customer account, the message did not appear in the customer Messages page.

## Root cause

Campaign contacts can exist as email-only CRM contacts. If a customer later creates a Microgifter account using the same email address, the existing `campaign_contacts.user_id` value can remain `NULL`. The CRM table showed those rows as `No account`, and `/api/merchant/crm-message.php` treated the send as an email fallback instead of adding the real customer account as a `message_thread_participants` participant.

That means the message was stored, but the customer account could not see it because canonical Messages visibility is participant-based.

## Fixes

- Resolve campaign contacts to user accounts by matching email when `campaign_contacts.user_id` is empty.
- Update resolved contacts so future CRM operations use the account relationship.
- Add the customer account as a canonical `message_thread_participants` participant before writing the CRM message.
- Send notification type `message` for account-delivered CRM messages.
- Show an inline delivery confirmation in the CRM message modal instead of closing immediately.
- Apply the same email-to-account resolution to bulk CRM messages.
- Classify Merchant CRM message threads in the customer communications dashboard.
- Add a data repair migration to backfill existing CRM message threads so previously invisible CRM messages become visible to matched customer accounts.

## Files touched

- `api/merchant/crm-message.php`
- `api/merchant/campaign-contacts.php`
- `api/merchant/crm-bulk-message.php`
- `api/communications/dashboard.php`
- `assets/js/merchant-crm.js`
- `database/stage_12_crm_message_account_link_repair.sql`
- `config/migrations.php`
- `scripts/validate_merchant_customer_messaging_audit.php`

## Expected behavior after deploy

- CRM contact rows with matching customer emails show as account contacts.
- Direct CRM messages deliver into the customer's `/messages.php` page.
- Merchant sees `Message delivered to customer Messages.` in the modal.
- Customer receives a normal message notification.
- Existing CRM threads are repaired during migration when the contact email matches a user account.
