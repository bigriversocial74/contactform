# Stage API-4 — Distribution issuance worker and INBOX delivery

This stage finalizes queued Distribution issuance jobs into PPPM items that appear in the recipient Microgifter INBOX.

## Worker entry points

- Web/manual: `/api/distribution/issuance-worker.php`
- CLI/cron: `php scripts/run_distribution_issuance_worker.php --limit=25`

## Processing flow

1. Claim queued or retryable `distribution_issuance_jobs`.
2. Resolve the allocation, program, recipient, and program product/template.
3. Create or reuse a PPPM source for Microgifter Distribution.
4. Create or reuse a PPPM source event and issuance request for the allocation.
5. Create one delivered `pppm_items` record for the job sequence.
6. Create an accepted `pppm_assignments` row for the linked recipient.
7. Create a delivered `pppm_deliveries` row and delivery attempt using provider `microgifter_inbox`.
8. Mark the Distribution job issued and link it to the PPPM item.
9. Update program, product, allocation, recipient, source event, and daily metrics counters.

## Inbox semantics

The recipient INBOX reads from `pppm_items` where `recipient_user_id` is the signed-in user and status is one of the active inbox states. The worker uses status `delivered`, sets `recipient_user_id`, and records a delivered API delivery so the item is immediately visible in the recipient INBOX.

## Retry behavior

Failures move jobs to `failed` with exponential backoff until `max_attempts` is reached. Exhausted jobs move to `dead_letter`; their allocation is marked failed unless it was already issued.
