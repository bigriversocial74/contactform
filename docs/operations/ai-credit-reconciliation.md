# AI Credit Reconciliation Operations

## Required migration

Import this migration after deploying the code:

```text
database/20260718_ai_credit_reconciliation_incidents.sql
```

The migration adds durable provider-response evidence, reconciliation run history, an idempotent incident queue, incident action history, and dedicated admin permissions.

## Scheduled reconciliation

Run the database-only reconciliation from the application root:

```bash
php scripts/run_ai_credit_reconciliation.php --days=30 --provider=anthropic --trigger=scheduled
```

Recommended production cron cadence is hourly:

```cron
0 * * * * cd /path/to/contactform && php scripts/run_ai_credit_reconciliation.php --days=30 --provider=anthropic --trigger=scheduled >> storage/logs/ai-credit-reconciliation.log 2>&1
```

The runner never calls an AI provider and never consumes AI credits. It compares durable provider responses, provider usage events, owner-credit ledger debits, response references, and Merchant Agent accounting security events.

## Incident types

The queue detects:

- Provider response without a ledger debit
- Ledger debit without provider evidence
- Provider and debited token mismatch
- Missing response reference
- Credit debit failure
- Missing preflight state
- Missing Merchant Agent accounting context

Incident keys are deterministic, so repeated runs refresh the same incident instead of creating duplicates. Resolved or dismissed mismatches reopen only when detected again. Mismatch incidents automatically resolve when a later run confirms that the mismatch no longer exists.

## Admin queue

Open:

```text
/admin/ai-credit-incidents.php
```

The page is linked from the Operations Command Center. Authorized admins can:

- Run reconciliation manually
- Filter active, under-review, resolved, and dismissed incidents
- Inspect merchant, source, model, response reference, token evidence, and history
- Assign incidents
- Move incidents under review
- Resolve, dismiss, or reopen incidents
- Retry eligible missing debits

## Controlled debit retry

Retry is limited to incidents with durable provider-response evidence and the original response reference. The existing `mg_ai_credit_consume` idempotency key includes the merchant, provider, Merchant Agent source, and original response reference. Repeating a retry cannot create a duplicate debit for the same response.

Every retry, resolution, dismissal, assignment, reopen, and automated state change is written to the incident action history. Administrative changes also write platform audit and security events.

## Merchant chat

Merchants can run:

```text
AI Report Alerts
AI Report Alerts 90 days
```

When the reconciliation migration is present, this command reports the signed-in merchant owner's active incidents directly in chat. It remains a free database query and does not expose admin controls or other merchants' data.
