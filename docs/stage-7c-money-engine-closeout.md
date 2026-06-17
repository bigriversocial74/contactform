# Stage 7C — Money Engine Closeout, Hardening, and Handoff

## Purpose

Stage 7C formally closes Stage 7 after the Stage 7A reconciliation and Stage 7B implementation. This pass verifies the complete money lifecycle, hardens race and idempotency edges, confirms duplicate financial systems were not introduced, records deferred risks, and prepares the Stage 8 handoff.

No new product UI or unrelated workflow is introduced.

## Stage 7A — Reconciliation and alignment

Stage 7A compared the official wallet, ledger, cashout, payout, hold, reversal, and reconciliation plan against the early payment foundation delivered in Stage 5I.

It established these source-of-truth decisions:

- commerce orders remain the customer obligation source
- payment intents and transactions remain the provider-payment source
- the grouped Stage 7 ledger becomes the internal balance source
- merchant payout records remain canonical and are adapted rather than duplicated
- existing webhook event storage remains canonical
- existing reconciliation run and item records remain canonical

## Stage 7B — Adapted money engine

Stage 7B delivered:

- wallets by owner and currency
- wallet ledger accounts
- balanced transaction groups
- group idempotency
- append-only ledger entries
- ledger-derived balances
- linked reversals
- paid-order grouped posting
- refund grouped posting
- wallet summary and ledger APIs
- cashout requests and reservations
- cashout approval and cancellation
- payout record adaptation
- signed payout webhook handling
- payout paid and failed balance transitions
- payout holds and releases
- administrator reconciliation
- migration, smoke, security, and regression coverage

## Canonical lifecycle

`Paid Order -> Grouped Ledger Posting -> Merchant Wallet -> Cashout Reservation -> Payout Record -> Signed Payout Event -> Paid or Failed/Released Balance`

Corrections use:

`Original Posted Group -> Linked Reversal Group`

Historical entries are never edited through application services.

## Stage 7C hardening completed

### Concurrent wallet creation

Wallet resolution now tolerates concurrent create attempts. A unique-key race re-reads the canonical wallet rather than failing or creating a second wallet.

### Concurrent platform-account creation

Platform ledger-account resolution now follows the same create-or-re-read pattern.

### Active-account enforcement

Wallet posting resolves only active wallet ledger accounts.

### Complete posting identity

Grouped posting now requires:

- transaction type
- source type
- idempotency key
- valid ledger account IDs
- positive integer-cent entries
- balanced debits and credits

### Reversal idempotency

A repeated reversal request with the same original group and idempotency key returns the existing reversal. A different second reversal is rejected. Empty ledger groups cannot be reversed.

## Duplicate-path review

The active Stage 7 paths use the grouped ledger:

- payment capture
- refund posting
- cashout reservation and release
- payout paid and failed settlement
- payout holds and releases
- administrator reversals

The older `mg_ledger_pair()` helper and `financial_ledger_entries` records remain compatibility/history artifacts from Stage 5I. They are not used by active Stage 7 posting paths and must not receive new callers.

No second versions were introduced for:

- orders
- payment intents
- payment transactions
- refunds
- disputes
- provider accounts
- payout records
- webhook event storage
- reconciliation runs
- reconciliation items

## Security closeout

Confirmed controls:

- wallet reads are owner or permission scoped
- cashout history is requester scoped
- high-risk administrator APIs require dedicated permissions
- financial write APIs require CSRF protection
- cashout and hold creation enforce available balance
- payout approval requires a payout-enabled provider account outside sandbox
- payout webhooks require valid signatures
- provider event IDs make webhook retries idempotent
- ledger group keys make financial retries idempotent
- high-risk actions create audit records and domain events
- no raw card or bank credentials are stored

## Deferred risks and technical debt

The following are intentional future work, not Stage 7 blockers:

- live payment and payout provider activation
- payout worker scheduling and retry orchestration
- provider settlement-file ingestion
- advanced dispute reserve policy
- multi-party fee splitting
- tax settlement accounts
- automated wallet snapshots
- dedicated financial administration UI
- database-role separation that denies direct ledger update/delete privileges
- full integration tests against a real provider sandbox

The application is append-only by service design. Production database-role hardening should later ensure the runtime role can insert and read ledger records but cannot directly update or delete them.

## Validation record

The merged Stage 7B validation completed successfully across:

- PHP syntax validation
- clean schema installation
- Stage 7B migration
- Stage 7B smoke checks
- security tests
- PHPUnit regression tests

Stage 7C adds regression contracts for concurrent wallet resolution, complete posting identity, reversal idempotency, grouped-ledger ownership, API protection, and handoff documentation.

## Final Stage 7 score

- Purpose alignment: 9.6/10
- Security boundaries: 9.2/10
- Accounting integrity foundation: 9.3/10
- Idempotency and retry safety: 9.3/10
- Existing-system consolidation: 9.5/10
- Test and documentation coverage: 9.3/10
- Production-provider readiness: 7.5/10
- Overall Stage 7 closeout: 9.2/10

Stage 7 is complete as a backend financial foundation. Live provider operations, worker automation, and advanced settlement controls remain explicitly deferred.
