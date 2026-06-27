# Store Canvas Phase 2 Messaging Integration

This phase makes Store Canvas customer communication use the existing Microgifter Messages and Notifications system as the canonical delivery path.

## Product rules

- Merchant Canvas direct messages are no longer only Store Canvas audit records.
- Merchant Canvas direct messages create or reuse a private `message_threads` conversation.
- The actual customer-visible message is written to `messages` with source metadata.
- Customer notifications use the existing `notifications` table and type `message`.
- Replies in `messages.php` keep Store Canvas source metadata when the thread originated from Store Canvas.
- Campaign-distributed rewards remain wallet/action-center items and surface in `inbox.php` through the existing IN/OUT Box action center merge.
- Inbox rows now show source metadata, such as `Source: Campaign Rewards` or `Source: IN/OUT Box`.

## Canonical systems

### Messages

Canonical delivery table:

- `message_threads`
- `message_thread_participants`
- `messages`
- `notifications`

Store Canvas audit mirror:

- `mg_agent_messages`

The audit mirror stores a link back to the canonical `thread_id` and `message_id` in metadata.

### Rewards

Campaign rewards continue to land in `wallet_items` and are merged into the user's `inbox.php` view by `api/account/action-center.php`.

Returned action center items include:

- `source_system`
- `source_type`
- `source_label`
- `source_detail`
- `source_reference`

## User-visible behavior

- Merchant sends a message from `merchant-canvas.php`.
- Customer receives a standard message notification.
- Notification opens `/messages.php?thread=<thread_id>`.
- Messages list and detail view show the source badge.
- Gift/Reward Inbox rows show source metadata in the row meta area.
