# Stage 5H — Notifications, Messaging, Recipient Communication, and Operational Alerts

Stage 5H extends the existing bell, message dropdown, and Inbox interfaces instead of creating a parallel communications system.

## Included

- Unified Inbox for gift and PPPM conversations
- Existing header notification and message badges retained
- Notification activity filters and read-state controls
- Operational alerts with severity, acknowledgement, resolution, and dismissal
- Gift, claim, delivery, distribution, campaign, merchant, security, and system preferences
- In-app, email, SMS, push, digest, quiet-hour, and timezone preference foundations
- Message pin, archive, and mute settings
- Delivery-job foundation for email, SMS, push, webhook, and in-app channels
- Recipient-safe destination hashes and provider delivery state
- Responsive integration with the existing agent/account UI

## Boundaries

Stage 5H does not introduce a third-party email, SMS, or push provider. It creates the queue, preferences, delivery state, and UI contracts required for later provider adapters.
