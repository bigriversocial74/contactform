# Profile Action Buttons Fix

## Issue

The Merchant CRM contact table message flow works, but the customer profile page action buttons did not reliably work. The profile page buttons could appear inactive because the page depended on a preloaded campaign contact ID and account link. If the profile was opened by CRM contact ID, email, or another profile route, the local page state could have an empty campaign contact ID even when a matching campaign contact existed.

## Fix

- Added a profile action resolver helper.
- Added `/api/merchant/profile-action-resolve.php`.
- The customer profile JavaScript now resolves the campaign contact link at submit time.
- Message, reward, and follow-up buttons are no longer silently disabled by stale profile state.
- The message panel now shows visible delivery proof.
- The reward panel now shows visible success or a clear account/link requirement.

## Expected behavior

From `/merchant-customer.php`:

1. Click **Message Customer**.
2. The panel opens.
3. Send message.
4. The panel shows `Message delivered.` with thread and message proof.

For rewards:

1. Click **Send Reward**.
2. The panel opens.
3. Select a reward template.
4. Send reward.
5. The panel shows `Reward sent.` with wallet item proof, or shows the exact reason it cannot send.
