# CRM Message Send Feedback Fix

## Reported issue

From Merchant CRM, pressing **Send message** animated the button and then stopped. No visible sent confirmation appeared, and the customer account still did not show the message.

## Root cause found

`api/merchant/crm-message.php` called `mg_message_validate_body()` but did not load the file that defines that helper. The helper lives in `api/gifts/_gift.php`. That can break the request before a valid JSON success payload is returned, which makes the UI look like the button simply stops.

## Fixes

- Added the missing `api/gifts/_gift.php` require to `api/merchant/crm-message.php`.
- Replaced the indirect delivery-proof wrapper with a deterministic submit handler in `assets/js/merchant-crm-messages.js`.
- The handler now intercepts the CRM direct-message form before the older handler, sends the request once, and always writes visible status into the modal.
- Success requires endpoint proof: thread ID and message ID.
- Success UI shows:
  - delivered to customer Messages or queued for email fallback
  - thread ID
  - message ID
  - notification ID when created
  - customer user ID when resolved
- Failure UI shows a visible error and identifies `/api/merchant/crm-message.php` as the failing endpoint.

## Expected behavior

After this PR, the merchant should see a visible confirmation inside the same modal after clicking **Send message**. The modal should not silently reset without proof.
