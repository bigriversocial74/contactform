# Loyalty Quest Notifications and Transactional Delivery v1

## Purpose

This section connects Loyalty Quest lifecycle events to Microgifter's existing in-app notification and durable message-delivery authorities. It does not create a second wallet, claim, or ownership lifecycle.

Participant rewards still move through the internal Wallet staging record into Microgift, PPPM, and the participant Inbox. Notifications report those canonical transitions.

## Database migration

Apply the registered migration through the standard Microgifter migration runner:

`database/loyalty_quest_notifications_transactional_delivery_v1.sql`

The migration adds delivery-attempt evidence, provider callback evidence, user suppression records, and merchant/campaign/source references on `message_delivery_jobs`.

Do not remove the file from `config/migrations.php`. The full-upgrade builder and migration manifest validator require it.

## Worker and cron

Run the CLI worker from the application root with the production PHP binary:

```bash
php scripts/run_loyalty_quest_notifications.php --limit=50
```

Recommended HostGator cron cadence:

```cron
*/5 * * * * /usr/local/bin/php /absolute/path/to/scripts/run_loyalty_quest_notifications.php --limit=50 >> /absolute/path/to/storage/logs/loyalty-quest-notifications.log 2>&1
```

Adjust the PHP and application paths to the hosting account. The worker uses a MySQL advisory lock, so overlapping cron runs exit without processing the same queue concurrently.

The worker processes:

- Loyalty Quest expiration notices within 48 hours
- issued Loyalty Quest reward expiration notices within 72 hours
- participant and merchant redemption receipts projected from canonical `wallet_item.redeemed` campaign events
- due email jobs from the shared `message_delivery_jobs` queue

An authenticated administrator can also inspect or run the worker through:

`/api/communications/loyalty-quest-worker.php`

## Merchant operations

Merchants use `/merchant-loyalty-quest-delivery.php` to:

- select an active Loyalty Quest
- send invitations to deliverable campaign contacts
- see queued, retrying, delivered, failed, suppressed, and dead-letter jobs
- retry a failed or dead-letter Loyalty Quest delivery

Invitations enforce merchant ownership, campaign status, package Email Stamp entitlement, contact deliverability, campaign suppression, CSRF protection, and a maximum batch size of 100.

## Preferences and quiet hours

Authenticated participants and merchants control Loyalty Quest in-app and email delivery under `/notification-preferences.php`.

The delivery service honors:

- channel enablement
- `immediate`, `hourly`, `daily`, `weekly`, and `off` modes
- timezone
- quiet hours
- user-level message suppression
- campaign email unsubscribe records

Non-account invitation recipients receive a campaign unsubscribe link and do not receive an in-app notification until they have an account.

## Mail provider

The worker uses the existing provider-neutral mail adapter in `includes/mail.php`.

Production delivery requires a working mail configuration. Log mode records accepted jobs without sending external email. PHP `mail()` mode depends on the hosting account's mail configuration. SMTP/API provider support remains an adapter point and must be configured separately before claiming live external delivery.

## Security and payload rules

Loyalty Quest delivery payloads include only the rendered subject/body, internal campaign references, event type, and non-secret recipient snapshot data.

They must not contain:

- QR signing secrets
- signed QR payloads
- claim codes
- voucher tokens
- passwords or authorization headers
- participant proof URLs or raw evidence
- location coordinates

The shared delivery redactor replaces secret-shaped fields before event or job persistence.

## Verification after deployment

1. Run the standard migration status check.
2. Run `php scripts/run_loyalty_quest_notifications.php --limit=1` and confirm valid JSON output.
3. Open the merchant delivery workspace and verify campaign/contact loading.
4. Queue one invitation to a test contact.
5. Run the worker and confirm the job reaches `delivered`, `retrying`, `failed`, or `dead_letter` with an attempt record.
6. Complete a test Loyalty Quest and confirm the reward appears in Microgifter Inbox before relying on the reward notification.
7. Redeem through the canonical merchant scanner and confirm participant and merchant receipt events are queued.

Live provider delivery, production database migration, and browser rendering require environment-specific verification after deployment.
