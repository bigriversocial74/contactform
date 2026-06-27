# Store Canvas Phase 2 Smoke Test

Use this checklist after deploying the branch and importing the current canonical migrations.

## Merchant Canvas message path

1. Log in as a customer account.
2. Open the feed.
3. Click `Enter Store` on a merchant feed post.
4. Log in as the merchant in another browser/session.
5. Open `/merchant-canvas.php`.
6. Confirm the customer avatar appears.
7. Open the customer CRM drawer.
8. Send a message from the drawer.
9. Confirm the customer receives a standard message notification.
10. Open `/messages.php?thread=<thread_id>` from the notification.
11. Confirm the thread shows a `Store Canvas` source badge.
12. Send a reply from the customer.
13. Confirm the merchant sees the reply in the same thread and the reply retains Store Canvas source metadata.

## IN/OUT Box reward source metadata

1. Open `/inbox.php` as a user with campaign wallet rewards.
2. Confirm campaign-distributed reward rows are visible.
3. Confirm the row metadata includes a `Source:` entry.
4. Open `/sent.php` and `/claimed.php` to confirm source metadata script does not break those folders.

## Expected tables touched

Canonical messaging:

- `message_threads`
- `message_thread_participants`
- `messages`
- `notifications`

Store Canvas session/audit:

- `mg_store_session_events`
- `mg_agent_messages` optional audit mirror

Campaign rewards:

- `wallet_items`
- `campaign_events`
