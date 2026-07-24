# Unified POS Customer & Purchase Sync v1 — QA and Reconciliation Plan

## 1. Test strategy

The feature requires four complementary test layers:

1. Static contract tests for files, routes, permissions, provider registry, SQL, and cron command.
2. Unit tests for normalization, signatures, identity matching, monetary calculations, and effect keys.
3. Database integration tests for transactions, queue locking, idempotency, CRM projection, refunds, and reconciliation.
4. Provider fixture tests using official/current sample payloads and captured Sandbox fixtures with secrets removed.

No provider phase is complete with syntax checks alone.

## 2. Canonical fixture

### Merchant

```text
Merchant A
Square connection: active
Environment: Sandbox
Locations:
- Downtown: enabled
- Airport: enabled
- Test Outlet: disabled
Currency: USD
```

### Existing Microgifter CRM contacts

```text
Contact 1
- Email: jamie@example.com
- Phone: 6025550100
- Existing Microgifter user: yes

Contact 2
- Email: alex@example.com
- Phone: 6025550200
- Existing Microgifter user: no

Contact 3
- Email: conflict@example.com
- Phone: 6025550300
```

### Square customers

```text
Square Customer A
- Exact email match to Contact 1
- Expected match method: email or Microgifter user after canonical resolution

Square Customer B
- Exact phone match to Contact 2
- Expected match method: phone

Square Customer C
- New email/phone
- Expected: new Merchant CRM contact

Square Customer D
- Email points to Contact 1
- Phone points to Contact 3
- Expected: ambiguous review

Square Customer E
- No email or phone
- Expected: new unmatched/matched CRM contact only when enough provider identity exists; no name-only merge

Square Customer F
- Email unsubscribed
- Expected: provider unsubscribe preserved; Microgifter marketing consent not granted
```

### Transactions

#### Transaction T1 — identified completed purchase

```text
Customer: Square Customer A
Location: Downtown
Subtotal: 4200
Discount: 500
Tax: 320
Tip: 800
Service charge: 0
Gross paid: 4820
Merchandise LTV: 3700
Items:
- Family Meal, net 3700
```

#### Transaction T2 — anonymous completed purchase

```text
Customer: none
Location: Airport
Subtotal: 1800
Tax: 150
Tip: 300
Gross paid: 2250
Merchandise LTV: 1800
```

Expected: merchant aggregate only; no CRM contact LTV.

#### Transaction T3 — later identity enrichment

Initially same as anonymous. A later `payment.updated` adds Square Customer B.

Expected:

- One completion effect only.
- Later identity-enrichment effect.
- CRM purchase projected once to Contact 2.

#### Transaction T4 — partial refund

```text
Original merchandise LTV: 5000
Gross paid: 5600
Completed refund: 2000
Allocated merchandise refund: 1800
Resulting merchandise LTV: 3200
```

#### Transaction T5 — full refund

```text
Original merchandise LTV: 2500
Completed refund removes all LTV
Canonical transaction status: refunded
Original transaction retained
```

#### Transaction T6 — gift-card sale

```text
Gift card item: 5000
Tax/tip: 0
Gross paid: 5000
Merchandise LTV: 0
Gift-card sale cents: 5000
```

#### Transaction T7 — disabled location

Expected:

- Durable receipt and canonical audit record according to final policy.
- No CRM/reward projection.
- Explicit ignored-by-location metadata.

## 3. Expected fixture totals

For identified customer-level LTV, excluding anonymous T2 and gift-card T6:

```text
Contact 1:
- Purchase count: 1
- Gross paid: 4820
- Merchandise LTV: 3700

Contact 2 after T3 enrichment:
- Purchase count: 1
- Merchandise LTV: transaction T3 merchandise value

T4 contact:
- Purchase count: 1
- Gross paid preserved
- Refund total: 2000
- Merchandise LTV: 3200

T5 contact:
- Purchase count: 1 according to completed-purchase history
- Net paid/LTV: 0 after full refund
```

Merchant aggregate analytics include anonymous transactions and gift-card gross paid while keeping their LTV classification separate.

## 4. Signature tests

### Square

- Valid signature using exact configured notification URL and raw body.
- Invalid signature.
- Correct signature generated for a different URL.
- Correct signature generated for reformatted JSON rather than raw body.
- Missing signature header.
- Timing-safe comparison path.
- Sandbox and production keys cannot cross-validate.

### Future providers

Equivalent current-provider signature fixtures are required before release.

## 5. Webhook delivery idempotency

Test:

1. Insert valid new Square event ID and payload.
2. Deliver same event ID and identical payload.
3. Confirm one receipt/job/business effect.
4. Deliver same event ID with changed payload.
5. Confirm conflicting replay quarantine and no trusted business processing.

## 6. Transaction revision idempotency

- Repeated completed `payment.updated` with identical normalized hash.
- Repeated completed event with newer provider update timestamp but no business change.
- Newer event adds customer identity.
- Older event arrives after newer event.
- Newer event adds fee/non-LTV metadata.
- Ensure current canonical state converges without repeated completion.

## 7. Business-effect idempotency

- Completion effect unique per transaction.
- Each refund effect unique by external refund ID.
- Identity enrichment unique by stable provider revision/version.
- Line-item enrichment unique by order/provider revision.
- Retry after transaction commit but before job status update returns existing effects.
- Outbox publication is idempotent.

## 8. Queue and concurrency tests

- Two workers attempt to claim the same job.
- Several workers claim different jobs safely.
- Worker crashes after lock acquisition.
- Stale lock recovery after configured threshold.
- Provider rate limit schedules retry with backoff.
- Connection token expires mid-job.
- Connection revoked while job is queued.
- Feature disabled while jobs remain queued.
- Job reaches maximum attempts and dead-letters.
- One-minute cron overlaps with a still-running prior worker.

Expected: no double processing, no lost job, no indefinite lock.

## 9. Customer import tests

- Empty directory.
- Single page.
- Multiple pages/cursors.
- Duplicate customer across resumed cursor.
- Customer updated during import.
- Import interrupted and resumed.
- Customer with invalid email but valid phone.
- Customer with valid email but invalid phone.
- Name-only customer.
- Deleted customer.
- Provider customer merge.
- Custom attributes enabled/disabled.
- Attribute allowlist.
- Sensitive-looking custom attribute excluded.
- Email unsubscribe preserved.
- Import creates no Microgifter login accounts.

## 10. Identity-resolution tests

Resolution priority:

```text
external mapping
→ Microgifter user/canonical account
→ exact email
→ exact phone
→ new CRM contact
→ ambiguous review when signals conflict
```

Cases:

- Existing mapping wins even when email changes.
- External mapping resolves through CRM contact merge.
- Email case normalization.
- Phone punctuation/country-code normalization.
- Same name, no other signal: no auto-merge.
- Email and phone resolve to different contacts: ambiguous.
- Manual link resolution.
- Manual create-new resolution.
- Ignore resolution.
- Merchant A external customer cannot resolve to Merchant B CRM contact.

## 11. Anonymous purchase tests

- No customer ID and no trusted buyer contact.
- Anonymous transaction contributes to merchant aggregate gross/net analytics.
- No CRM contact created.
- No customer LTV updated.
- Later customer ID attaches transaction.
- Later identity event does not apply completion twice.
- Anonymous transaction remains anonymous after unrelated customer-directory event.

## 12. Monetary/LTV tests

- Discounts.
- Inclusive/exclusive taxes.
- Tips.
- Service charges default excluded.
- Service-charge setting enabled followed by explicit reconciliation.
- Partial quantity/decimal item.
- Multi-currency connections.
- Currency mismatch in one connection/transaction.
- Gift-card sale exclusion.
- Gift-card redemption classification.
- Rounding allocation across line items.
- Refund allocation across discounted line items.
- Negative/invalid provider totals rejected or quarantined.

## 13. Refund tests

- Refund created/pending: no LTV adjustment.
- Refund completed: adjust once.
- Repeated completed refund event.
- Partial refund followed by another partial refund.
- Full refund.
- Failed refund after pending.
- Refund arrives before completion event.
- Refund references unknown transaction.
- Provider correction changes refund amount.
- Refund causes transaction status to become partially refunded/refunded.
- Reward outbox gets refund event but provider adapter does not revoke reward.

## 14. Line-item tests

- Order retrieval succeeds.
- Order ID missing.
- Order retrieval returns no line items.
- Provider API temporarily unavailable.
- Stable external line IDs.
- Deterministic generated line key when no stable ID.
- Duplicate line-item delivery.
- Catalog ID and SKU preserved.
- Gift-card classification.
- Item totals reconcile with order/payment.
- Unexplained discrepancy is surfaced, not silently hidden.

## 15. CRM projection tests

- Identified completion writes one timeline event.
- CRM lifecycle becomes customer through canonical service.
- Last purchase updated to latest occurred/completed timestamp.
- POS rollup updates.
- Refund timeline event and rollup change.
- General CRM and POS-specific totals do not overwrite unrelated non-POS purchase history incorrectly.
- CRM contact merge transfers/recomputes POS rollup.
- Recompute matches incremental results.
- Transaction remains source of truth after CRM record edit/archive.

## 16. Outbox/event bridge tests

- Completion inserts one pending outbox event in same transaction.
- Publisher retry.
- Duplicate publication prevented.
- Refund event includes effect key and adjusted values.
- Disabled reward bridge leaves canonical purchase intact.
- Provider adapter cannot call reward issuance services.

## 17. OAuth and credential tests

- Valid Square OAuth state.
- Expired state.
- Replayed state.
- State initiated by another merchant/session.
- Missing required scopes.
- Token refresh.
- Provider revocation.
- Encryption/decryption with active key version.
- Key rotation path.
- Credential values never returned by API or logs.
- Disconnect clears credentials and preserves history.

## 18. Merchant authorization tests

- Merchant owner access.
- Authorized merchant team member according to permissions.
- View-only staff cannot connect/disconnect/retry/repair.
- Merchant cannot access another merchant's connection, locations, customers, transactions, jobs, or sync runs.
- Selected-merchant entitlement enforced.
- Feature state enforced on all entry points.

## 19. Retention/privacy tests

- Redacted payload purge after 90 days.
- Hash/event ID/status remain.
- Secret/token fields redacted recursively.
- Imported provider unsubscribe does not become Microgifter marketing consent.
- Privacy erasure anonymizes identity while preserving required transaction totals.
- Deleted provider customer does not erase required purchase history.
- Disconnect does not delete normalized history.

## 20. Reconciliation command

Required CLI:

```text
php scripts/reconcile_pos_sync.php --provider=square --connection=<uuid> --dry-run
```

Additional options:

```text
--transaction=<uuid>
--customer=<uuid>
--from=<datetime>
--to=<datetime>
--limit=100
--repair
```

### Dry-run checks

- Receipt without job.
- Job without connection.
- Processed job without normalized object/effect.
- Duplicate completion effect.
- Missing/duplicate refund effect.
- Transaction/customer mapped across merchants.
- Customer mapping points to merged/noncanonical CRM contact.
- Completed identified transaction missing CRM event.
- POS rollup differs from canonical recomputation.
- Outbox event missing or duplicated.
- Stale processing job.
- Redacted payload beyond retention window.
- Anonymous transaction now resolvable.

### Repair rules

Safe deterministic repairs may:

- Create missing queue job from trusted receipt.
- Relink mapping to canonical merged CRM contact.
- Create missing CRM projection/outbox event when canonical effect is complete and unique.
- Recompute POS rollup.
- Release stale lock.
- Purge expired payload.

Unsafe repairs requiring manual review:

- Conflicting replay.
- Cross-contact identity conflict.
- Unknown provider financial state.
- Currency mismatch.
- Missing authoritative provider object.
- Ambiguous refund allocation.

Every repair writes an audit receipt with before/after hashes.

## 21. Load and operational tests

- Burst of 1,000 webhook deliveries.
- 100 duplicate deliveries.
- 100 events for one transaction lifecycle.
- 10 merchant connections processed concurrently.
- One-minute cron with worker time budget.
- Provider latency and timeout.
- Provider 429 rate limiting.
- Database deadlock retry.
- Large customer import with resumable cursor.
- Queue dashboard remains responsive.

## 22. CI workflow

Recommended workflow:

```text
.github/workflows/unified-pos-sync-v1.yml
```

Run:

- PHP 8.2 syntax/tests.
- PHP 8.3 syntax/tests.
- JavaScript syntax/tests.
- SQL single-install contract.
- Provider fixture tests.
- Webhook signature tests.
- Queue/idempotency/concurrency tests.
- CRM/LTV/reconciliation tests.
- Static route/permission/file contract.

## 23. Release gate

Square v1 cannot be released until:

- OAuth Sandbox connection passes.
- Initial customer import passes.
- Signature fixture passes.
- Completed payment passes.
- Duplicate and out-of-order delivery pass.
- Anonymous identity enrichment passes.
- Partial/full refunds pass.
- CRM rollups reconcile.
- One-minute worker is observed.
- Dry-run reconciliation reports zero unexplained drift.
- Selected-merchant feature state is active.
- SQL/configuration/deployment are explicitly confirmed.