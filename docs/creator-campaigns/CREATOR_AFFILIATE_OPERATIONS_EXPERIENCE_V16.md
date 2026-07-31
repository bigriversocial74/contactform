# Creator Affiliate Operations & Experience v16

## Purpose

This phase completes the operating layer around the certified Creator Campaign merchant-affiliate engine. It does not replace attribution, compensation, budgets, payout records, disputes, or the automatic paid-order/refund bridge. It makes those systems understandable and operable in production.

## Delivered scope

### Merchant payout operating policy

Each merchant workspace can define a currency-specific payout policy with:

- active or paused status
- manual, weekly, biweekly, or monthly cadence
- payout weekday or day of month
- commission hold period
- merchant-wide minimum payout
- payment-method label
- Creator-facing payment instructions
- dispute window
- mandatory manual approval

The database constrains `manual_approval_required` to `1`. No policy can authorize automatic transfer execution.

### Default policy

Until the merchant saves a policy, the runtime uses these safe defaults:

- currency: USD
- status: active
- cadence: manual
- commission hold: 7 days
- minimum payout: $25.00
- dispute window: 30 days
- manual merchant approval: required
- payment completion: external/provider-neutral
- provider reference: required before a payout can be marked paid

### Policy enforcement

Payout assembly requires:

1. an eligible Creator payout profile
2. an active merchant payout policy
3. committed campaign-budget reservations
4. completion of the policy hold period
5. a committed balance at or above the larger of:
   - the merchant policy minimum
   - the Creator-specific profile minimum
6. no active reservation dispute

A payout begins in `draft`, must be approved by the merchant, and cannot be marked `paid` without an external provider reference.

Pausing a policy blocks new payout assembly and transitions into `approved` or `processing`. It does not prevent an already-processing external payment from being accurately recorded as `paid`; that final transition still requires the provider reference and remains blocked by an active dispute.

Each explicit payout-creation action receives its own idempotency key. The same key is retained for a network retry, but a later deliberate payout action receives a new key. This prevents duplicate drafts without imposing a one-payout-per-day limitation.

### Reconciliation monitoring

The Operations Center can scan and persist cases for:

- paid orders whose Creator affiliate lifecycle stopped before completion
- attributed purchases with no earning
- Creator earnings with no campaign-budget reservation
- successful refunds with no Creator commission adjustment
- failed, stale-processing, or invalid paid payout records
- open Creator finance disputes
- repeated suspect tracking activity

Cases are fingerprinted and persistent. Repeated scans update the existing case rather than creating duplicates. A clean scan resolves previously open cases that are no longer detected. If any detector fails, the scan records a critical scanner error and does not auto-resolve other cases.

The CLI reconciliation runner uses a database advisory lock, scans workspaces independently, emits structured JSON, and returns a nonzero status for detector or workspace failures. It records reconciliation evidence only; it does not modify orders, earnings, reservations, payouts, or disputes.

### Merchant setup experience

`/merchant-creator-affiliate-operations.php` provides:

- payout policy setup
- program metrics
- campaign readiness scoring
- direct links to fix readiness blockers
- guided Creator eligibility configuration
- guided payout assembly without manually entering participant IDs
- persistent reconciliation queue
- acknowledge, resolve, and ignore actions
- direct links to the responsible operational workspace

Campaign readiness is scored across:

- scheduled/active lifecycle
- commissionable product relationship
- active purchase-attributed compensation rule
- active funded campaign budget
- active approved Creator
- active Creator tracking source

Creator payout readiness distinguishes:

- total committed balance
- balance still inside the hold period
- payout-ready balance
- the effective merchant/Creator minimum
- the next held-fund eligibility time

The interface enables payout creation only when its policy, participant, eligibility, hold, balance, and minimum checks match the locked payout service.

### Creator experience

Creator earnings now show:

- net earnings
- budget-reserved amount
- committed amount
- payout-scheduled amount
- processing amount
- paid amount
- refund/correction adjustments
- earning, reservation, and payout record IDs
- provider reference and paid date
- a plain-language lifecycle status guide

Creator payouts now show:

- the merchant name for each policy
- merchant payout cadence
- next scheduled date when applicable
- hold period
- minimum payout
- dispute window
- payment method and instructions
- eligibility status
- draft, approved, processing, paid, failed, cancelled, and reversed explanations
- dated payout timeline
- provider reference
- dispute status and resolution

Only the merchant workspace display name is included in Creator policy cards. Merchant owner identity, contact details, and private account fields are not exposed.

## Payout boundary

This phase does not:

- call Stripe transfers or another payment-provider transfer API
- store bank account credentials
- file or calculate tax forms
- bypass merchant approval
- mark a payout paid without an external provider reference
- grant MCP payout authority
- schedule autonomous payout execution

## Refund and clawback behavior

The existing certified commerce bridge remains authoritative:

- successful refunds create proportional append-only negative earning adjustments
- reserved or committed budget obligations are reduced or released
- draft or approved payouts are cancelled when their underlying obligation changes
- processing, paid, or reversed payout records create a dispute instead of being silently rewritten
- retries reuse canonical adjustment, budget, payout, and dispute evidence

## Migration

Import after the Creator Campaign Phase 8 payout tables and the Phases 1–15 production audit repair:

`database/20260730_creator_affiliate_operations_experience_v16.sql`

The migration is additive and idempotent and is registered as a manual-only migration because Creator Campaign Phases 6–15 are also applied through their ordered manual install chain.

## Deployment order

1. Merge the approved pull request into `integration-from-repair-20260628`.
2. Import the v16 SQL migration.
3. Deploy the merged runtime files while preserving production configuration and runtime directories.
4. Open Affiliate Operations and save the merchant payout policy.
5. Configure the reconciliation runner in the server scheduler.
6. Run reconciliation.
7. Perform a controlled purchase, refund, and payout-record smoke test.

## Production smoke test

1. Open an active Creator Campaign with a commissionable product, purchase compensation rule, active budget, approved Creator, and active tracking source.
2. Complete a tracked paid purchase.
3. Verify attribution, earning, reservation, merchant operations status, and Creator earnings status.
4. Commit the reservation and verify the Creator status becomes committed.
5. Configure Creator payout eligibility.
6. Wait for or temporarily configure the hold period, then confirm the Operations Center separates held and payout-ready funds.
7. Create a payout from the guided participant action.
8. Verify the record starts in draft and requires approval.
9. Move it through approved and processing.
10. Confirm it cannot be marked paid without a provider reference.
11. Pause the policy after processing begins and confirm the existing payout can still be recorded paid with a synthetic external reference in a non-production test.
12. Confirm a second deliberate payout action can create a new draft while a retry of the first action remains idempotent.
13. Test a partial refund before payout processing and verify adjustment/release behavior.
14. Test a refund after payout processing and verify a dispute is created.
15. Run reconciliation and verify there are no unexplained critical cases.
