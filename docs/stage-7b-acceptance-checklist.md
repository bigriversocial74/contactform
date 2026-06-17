# Stage 7B Acceptance Checklist

## Wallet and ledger

- [x] Wallets are unique by owner type, owner user and currency.
- [x] Required wallet ledger accounts are created automatically.
- [x] Transaction groups require a unique idempotency key.
- [x] Ledger posting rejects unbalanced groups.
- [x] Ledger entries require positive integer-cent amounts.
- [x] Ledger entries are inserted atomically with the group.
- [x] Ledger mutations are controlled through append-only service methods and reversal entries.
- [x] Wallet balances are calculated from the ledger.
- [x] Reversals create opposite entries and preserve original history.

## Payment integration

- [x] Paid orders use the grouped Stage 7 ledger.
- [x] Paid-order ledger posting is idempotent by order identity.
- [x] Existing receipt and PPPM issuance behavior remains intact.
- [x] Successful refunds use grouped ledger postings.
- [x] Refund posting is idempotent by refund identity.

## Cashouts and payouts

- [x] Cashout requests require authentication and CSRF protection.
- [x] Cashout amount must be positive.
- [x] Cashout cannot exceed available balance.
- [x] Valid cashout reserves funds in the ledger.
- [x] Cashout requests are idempotent.
- [x] Approval creates one existing-model payout record.
- [x] Non-sandbox approval requires an active payout-enabled provider account.
- [x] Signed payout webhook events are provider-event idempotent.
- [x] Paid payout events finalize cashout and wallet balances.
- [x] Failed payout events release reserved funds.

## Holds, reversals and reconciliation

- [x] Authorized admins can create payout holds.
- [x] Holds reduce available balance and increase held balance.
- [x] Releases restore held value through a second posting.
- [x] Authorized admins can reverse posted transaction groups.
- [x] Reconciliation reuses existing run and item tables.
- [x] Reconciliation records order-to-wallet-ledger mismatches.

## Security

- [x] Wallet reads are owner or permission scoped.
- [x] Cashout history is requester scoped.
- [x] Admin cashout, hold, reversal and reconciliation actions require permissions.
- [x] Write APIs require CSRF protection.
- [x] Payout webhooks require a valid signature.
- [x] No raw card or bank credentials are stored.
- [x] Live provider calls remain disabled behind provider configuration.

## Validation

- [x] Stage 7B migration runner exists.
- [x] Stage 7B schema smoke checks exist.
- [x] PHPUnit contract coverage exists.
- [x] Consolidated PR Validation runs Stage 7B migration and smoke checks.
- [ ] PR Validation passes on GitHub.
- [ ] Main Regression passes after merge.
