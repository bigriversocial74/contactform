# Personal Agent Follow-Up and Recovery v1

## Purpose

This stage extends the Personal Agent opportunity attribution ledger with customer-controlled follow-ups for saved opportunities, unfinished cart and checkout activity, expiring campaigns, and unavailable products.

It does not create a second cart, checkout, payment, campaign, or notification system. Recovery events remain connected to the canonical opportunity, commerce, campaign, order, and notification records.

## Deployment

1. Import `database/stage_18al_personal_agent_followup_recovery_v1.sql`.
2. Deploy the application code.
3. Configure the scheduled worker to run every 15 minutes:

```bash
php scripts/process_personal_agent_opportunity_recovery.php 100
```

The admin-only endpoint `api/communications/personal-agent-recovery-worker.php` provides worker status and a protected manual run action.

## Customer controls

Customers can:

- Enable or disable automated follow-ups.
- Independently control saved, cart/checkout, campaign-expiry, and unavailable-item reminders.
- Set a weekly frequency limit and cooldown.
- Set timezone and quiet hours.
- Schedule a reminder from a recommendation or Saved Opportunities.
- Snooze, dismiss, or permanently mute an opportunity.

Manual reminders remain customer-directed and do not authorize a purchase, send, claim, redemption, campaign entry, or charge.

## Automation lifecycle

- Saving an opportunity may schedule a saved-opportunity follow-up.
- Adding an attributed product to cart may schedule cart recovery.
- Starting checkout cancels cart recovery and may schedule checkout recovery.
- Completing a purchase or campaign entry marks active follow-ups as converted.
- Hiding an opportunity mutes its follow-ups.
- Scheduled scans backfill missed event hooks, find expiring campaigns, and detect unavailable products.
- Delivery applies customer frequency caps, cooldown, quiet hours, and canonical notification preferences.

## Privacy boundary

Merchants receive aggregate recommendation, delivery, recovery, conversion, and recovered-revenue metrics. They do not receive Personal Agent conversation text, private reminder text, private contact data, or individual customer reminder preferences.

## Operational smoke test

1. Save an attributed Personal Agent opportunity.
2. Schedule a manual reminder.
3. Confirm it appears in the Personal Agent Reminders view.
4. Snooze, dismiss, and mute separate test reminders.
5. Run the worker manually or through the CLI.
6. Confirm one in-app notification is created and duplicate worker runs do not duplicate delivery.
7. Add an attributed product to cart, start checkout, and complete a test order.
8. Confirm active recovery follow-ups become converted and merchant recovery metrics update.
