# Admin commerce operations center

The protected workspace at `/commerce-operations.php` provides one operational view across the existing commerce and Microgift lifecycle systems.

## Supported domains

- commerce orders and payment intents;
- payment transactions, refunds, and disputes;
- subscriptions, payment attempts, and recovery activity;
- universal tips and canonical ledger-backed reversals;
- Microgift issuance, delivery, claim, redemption, and lifecycle actions;
- internal commerce review cases.

The workspace reads from the canonical domain tables. It does not create a second payment, subscription, ledger, or Microgift lifecycle implementation.

## Permissions

- `admin.commerce.view` — access the cross-domain queue, detail drawer, lifecycle timeline, and related records;
- `admin.commerce.manage` — open, assign, document, resolve, dismiss, and reopen commerce review cases;
- `tips.reverse` — reverse a posted tip through the existing ledger reversal service.

The Stage 18L migration grants the commerce view and management permissions to the `admin` and `super_admin` roles. Existing payment, subscription, Microgift operations, and tip-reversal permissions continue to provide read access where appropriate.

## Safeguards

- queue and detail responses are private and non-cacheable;
- list and detail requests are rate limited and bounded;
- every write requires CSRF validation, a reason of 8–500 characters, and confirmation in the interface;
- case mutations use transactions and row locks;
- successful actions create audit, domain-event, and security records;
- tip reversal delegates to `mg_tip_reverse()` and the canonical ledger authority;
- no password hashes, token hashes, provider secrets, or raw authentication material are returned.

## Deployment

Run the canonical migration runner after deployment:

```bash
php scripts/run_migrations.php
```

This applies `database/stage_18l_admin_commerce_operations.sql` through `config/migrations.php`.
