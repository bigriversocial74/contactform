# Profile Action Buttons Fix

## Issue

The Merchant CRM contact table message flow works, but actions from `/merchant-customer.php` were not reliable. The customer profile page depended on a campaign contact ID that was already present in page state. If the profile was opened by CRM contact ID, email, wallet item, or another route, message/reward buttons could appear to do nothing because the profile did not resolve the active campaign contact at action time.

## Fix

- Added `api/merchant/_profile_action_resolver.php`.
- Added `api/merchant/profile-action-resolve.php`.
- Added `assets/js/merchant-customer-profile-actions-fix.js`.
- Updated `merchant-customer.php` to load the action-fix script after the main profile script.

## Behavior

The profile action script now:

- Keeps profile action buttons clickable instead of silently disabled.
- Resolves the campaign contact link at submit time.
- Links campaign contacts to existing user accounts by matching email.
- Sends profile messages through the same working CRM message endpoint.
- Sends profile rewards through the existing CRM gift endpoint.
- Shows visible success or failure inside the profile panel.

## Expected result

From `/merchant-customer.php`:

- **Message Customer** opens the panel and sends through `/api/merchant/crm-message.php`.
- **Send Reward** opens the panel, resolves the profile link, and sends through `/api/merchant/crm-send-gift.php` when the customer has a linked account.
- If a reward cannot be sent, the panel now shows the exact reason instead of the button doing nothing.
