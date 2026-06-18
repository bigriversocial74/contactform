# Recipient notifications

Microgifter creates recipient notifications for three direct user actions:

- A member follows another public profile.
- A participant sends a message in a gift or Microgifter item thread.
- A user sends a gift or issues a Microgift to another account.

## Preference categories

These events use the existing notification categories:

- `social` for follows
- `message` for conversation messages
- `gift` for gifts and Microgifts

Recipients can enable or disable in-app, email, SMS, and push delivery for each category. Digest timing and quiet hours are applied when delivery jobs are queued. Turning a category off suppresses new notification records and delivery jobs for that category.

## Deduplication

Every direct recipient event carries a durable event key:

- Follow notifications include the follower, recipient, and relationship activation version.
- Gift notifications use the gift public identifier.
- Microgift notifications use the Microgift instance public identifier.
- Message notifications aggregate by conversation thread.

Exact retries return the existing notification instead of creating a duplicate. A new message in an existing thread updates the existing thread notification, increments its occurrence count, and marks it unread again.

## Thread controls

Message notifications also honor each participant's thread settings. A muted thread or a thread with notifications disabled does not generate recipient notifications, even when the global `message` category is enabled.

## Action links

Notification actions are restricted to internal Microgifter URLs. Opening an unread notification from the notification center marks it read before navigating to the profile, message thread, gift, or Microgift.

## Delivery scheduling

In-app activity is available immediately. External delivery jobs use the recipient's digest preference:

- Immediately
- Hourly
- Daily at 8:00 AM in the selected timezone
- Weekly on Monday at 8:00 AM in the selected timezone

Quiet hours delay external delivery until the configured quiet period ends.
