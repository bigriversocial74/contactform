# Creator Campaign Phases 1–15 — Production Audit & Repair v1

## Scope

This audit covers the complete Creator Campaign feature family and the shared systems it directly relies on:

- Phase 1 native campaign foundation
- Phase 2 merchant builder
- Phase 3 participation, applications, invitations, participants, and agreements
- Phase 4 deliverables, submissions, revisions, and content review
- Phase 5 tracking and attribution
- Phase 6 compensation and append-only earnings
- Phase 7 budgets, reservations, commitments, and bucket ledger
- Phase 8 payout records and disputes
- Phase 9 canonical messaging and notifications
- Phase 10 analytics and exports
- Phase 11 merchant and Creator production UI
- Phase 12 CRM lifecycle projection
- Phase 13A–13D MCP read, draft, approval-gated action, and bounded-playbook layers
- Phase 14 production pilot and emergency controls
- Phase 15 merchant onboarding and launch-readiness receipts
- shared identity, user-model, merchant-workspace, permissions, CSRF, audit, event, catalog, PPPM, and migration contracts used by those phases

Unrelated Microgifter modules are excluded except where a shared dependency is needed to validate Creator Campaign behavior.

## Executive result

The architecture remains sound: canonical records are reused, financial records are append-only, budget movements are transactionally bounded, participant agreements are immutable, MCP authority remains owner-granted and fail-closed, and Phase 15 activation does not publish a campaign or execute an external effect.

The audit identified and repaired six production defects or hardening gaps.

## Findings and repairs

### A-01 — Canonical Creator permissions were incomplete for customer-role accounts

**Severity:** High  
**Affected phases:** 3, 4, 5, 6, and 8

Creator is a user model layered on Microgifter's universal `customer` role. Phase 9 and later code correctly follows this identity model, but several earlier migrations granted Creator own-record permissions only to the legacy `creator` role.

The repair migration grants the affected permissions to `customer` as well. This does not create broad access: runtime services still require an active user, active Creator model assignment, active Creator profile, Creator model context, platform permission, and object ownership.

Backfilled permissions:

- `creator.campaigns.discover`
- `creator.campaign_applications.manage_own`
- `creator.campaign_invitations.respond_own`
- `creator.campaign_participants.view_own`
- `creator.campaign_agreements.view_own`
- `creator.campaign_agreements.respond_own`
- `creator.campaign_deliverables.view_own`
- `creator.campaign_submissions.manage_own`
- `creator.campaign_tracking.view_own`
- `creator.campaign_tracking.manage_own`
- `creator.campaign_earnings.view_own`
- `creator.campaign_payouts.view_own`
- `creator.campaign_disputes.manage_own`

### A-02 — Percentage compensation converted integer money to floating point

**Severity:** High  
**Affected phase:** 6

Percentage earnings previously evaluated minor units and basis points through floating-point division. That is unnecessary and can introduce precision drift for large valid amounts.

The repair adds exact quotient-and-remainder integer arithmetic:

- all source values remain integer minor units
- all rates remain integer basis points
- the result uses floor semantics
- overflow is detected before multiplication
- existing minimum and maximum earning guards remain authoritative

Examples locked into the contract:

- 10,001 minor units at 1 basis point = 1 minor unit
- 19,999 minor units at 5,000 basis points = 9,999 minor units
- a 10,000-basis-point calculation returns the exact source amount

### A-03 — Manual earning adjustments and reversals were unique but not retry-safe

**Severity:** Medium-high  
**Affected phase:** 6

Database uniqueness prevented duplicate financial events, but repeated manual adjustment or reversal requests could surface a duplicate-key failure instead of returning the existing append-only event.

The repair now:

- checks deterministic idempotency under transaction lock
- returns the existing event on a legitimate retry
- handles concurrent duplicate-key races by rereading the canonical event
- preserves one reversal per original earning event
- does not mutate or delete prior financial evidence

### A-04 — Phase 15 smoke-test receipts could block a later pass for the same state

**Severity:** High  
**Affected phase:** 15

The original unique key identified a receipt by onboarding, receipt type, and state hash. A failed attempt and a passing attempt for the same state therefore collided.

The repair makes receipt identity status-aware:

`onboarding_id + receipt_type + snapshot_hash + status`

The smoke service also reads and writes the exact pass/fail status. Failed and passing evidence can coexist while identical retries remain idempotent.

### A-05 — Phase 15 receipt freshness did not cover every launch-critical change

**Severity:** High  
**Affected phase:** 15

The launch fingerprint now includes:

- product current-version identity
- product and version statuses
- exact price and currency
- ready-image count
- active PPPM-template count
- automatic-acceptance state
- primary operator account and workspace membership
- every assigned operator's account and workspace membership
- campaign state and canonical readiness evidence
- financial defaults and exposure result
- emergency-stop state

A prior passing receipt becomes stale when any of those values change. Readiness distinguishes the latest attempt from a current passing receipt.

### A-06 — Campaign operational links lost selected-campaign context

**Severity:** Medium  
**Affected phases:** 11 and 15

The Phase 11 builder and Phase 15 onboarding linked to live operational workspaces but did not consistently carry the selected campaign.

The repair:

- appends the campaign public ID to Phase 15 operational links
- propagates the campaign through Phase 11 builder and detail navigation
- applies the campaign only to the primary campaign filter on participation, deliverables, and tracking pages
- prefills the canonical campaign public ID for new compensation rules and budgets
- does not populate unrelated modal selectors

### A-07 — Concurrent first onboarding loads could raise a duplicate-key error

**Severity:** Medium  
**Affected phase:** 15

Onboarding creation now uses an idempotent insert-and-reread pattern. The creation event is emitted only by the request that actually creates the row.

## Confirmed architecture boundaries

The audit confirmed the following boundaries remain intact:

- Phase 15 activation changes only the onboarding record.
- Campaign publication remains a separate native lifecycle action.
- Participant agreement versions remain participant-specific and are created after merchant approval; the prelaunch check correctly validates agreement infrastructure rather than requiring a participant agreement before participants exist.
- Phase 6 uses append-only earnings and reversals.
- Phase 7 uses integer minor units, transactional row locks, and available/reserved/committed bucket events.
- Phase 8 records payout state but does not call a payment provider.
- Phase 10 analytics is read-only and does not create duplicate counters.
- Phase 12 CRM projection does not turn anonymous tracking hashes into contacts.
- Phase 13A–13D does not grant scopes automatically and does not allow an MCP client to approve or execute its own requested action.
- Phase 14 emergency clearing is non-restorative; paused grants and definitions remain paused for human review.
- No repair in this audit adds MCP scopes, grants, definitions, schedules, payment actions, or autonomous execution.

## Production workflow test matrix

| Workflow | Actor | Required setup | Expected result | Negative test |
|---|---|---|---|---|
| Merchant creates campaign draft | Merchant owner | Active merchant workspace | One canonical draft, idempotent replay returns same campaign | Unrelated merchant cannot read or edit it |
| Merchant configures Steps 1–3 | Merchant owner/authorized operator | Draft campaign | Lock version advances and canonical validation updates | Stale lock version fails closed |
| Creator discovers campaign | Active customer account with active Creator model/profile | Eligible scheduled or active campaign | Campaign appears under Creator ownership rules | Customer without active Creator model/profile is denied |
| Creator applies or responds to invitation | Eligible Creator | Application/invitation window open | One own-record application/invitation response | Creator cannot act on another Creator's record |
| Merchant approves participation | Authorized merchant operator | Capacity and eligibility valid | Participant and immutable agreement workflow proceeds | Automatic acceptance remains disabled for Phase 15 launch |
| Creator accepts agreement | Owning Creator | Current offered version | Immutable acceptance evidence activates participation | Superseded or another Creator's version is denied |
| Deliverable lifecycle | Merchant + owning Creator | Accepted agreement | Assignment, submission, revisions, review, proof | Revision limits and terminal states fail closed |
| Tracking and attribution | Merchant + owning Creator | Active participant/source | Privacy-safe events and one canonical attribution | Invalidated or suspect facts cannot create valid earnings |
| Compensation calculation | Merchant-owned campaign | Active immutable rule version | Exact integer earning event | Float drift, duplicate source, or invalid agreement blocked |
| Budget reservation | Merchant | Active budget, eligible earning | Atomic reservation and bucket event | Over-cap reservation fails unless controlled overage is enabled |
| Payout record | Merchant | Eligible committed reservations/profile | Internal payout record and immutable items | No provider call or bank credential access occurs |
| Creator dispute | Owning Creator | Own earning/reservation/payout | One active dispute per source | Other Creator's source is denied |
| Messaging | Merchant + participant | Canonical thread eligibility | One canonical campaign thread and standard notifications | Closed thread blocks new campaign messages |
| Analytics/export | Authorized merchant or owning Creator | Canonical Phase 3–9 records | Read-only scoped metrics and CSV | Creator never receives merchant budget balances |
| CRM projection | Merchant | Trusted participation/conversion evidence | Idempotent Creator/customer relationship projection | Anonymous hashes never create CRM identities |
| MCP read/draft | Owner-granted external client | Active connection and exact scope | Read receipt or review-only draft | No native mutation |
| MCP action request | Owner-granted external client | Approval-gated scope and policy | Waiting-for-owner-approval request | Client cannot approve or execute it |
| MCP bounded playbook | Owner | Active grant, definition, manual trigger | One review artifact and durable run evidence | No scheduler or canonical action request |
| Phase 14 emergency stop | Merchant owner | Active pilot | New runs blocked and bounded authority paused | Clear operation does not auto-resume grants |
| Phase 15 smoke test | Merchant owner | Steps 1–7 current | Status-aware immutable receipt | Product, role, financial, campaign, or emergency change invalidates pass |
| Phase 15 activation | Merchant owner | Current passing receipt | Onboarding becomes active | Campaign remains unpublished and no external effect occurs |

## Permission matrix

| Account/context | Creator own-record APIs | Merchant campaign APIs | Phase 15 owner actions | Admin scope |
|---|---:|---:|---:|---:|
| Anonymous | Denied | Denied | Denied | Denied |
| Customer without active Creator model/profile | Denied | Denied unless separately merchant-enabled | Denied | Denied |
| Customer with active Creator model and active Creator profile | Allowed only for own Creator records | Denied unless separately merchant-enabled | Denied unless workspace owner | Denied |
| Legacy Creator role with active Creator context | Allowed only for own records | Denied unless merchant-enabled | Denied unless workspace owner | Denied |
| Merchant workspace owner | Only when separately Creator-enabled | Allowed for owned workspace | Allowed for owned workspace | Denied |
| Active merchant team member | Only when separately Creator-enabled | Limited by native merchant permissions and workspace membership | Documented role does not itself grant Phase 15 owner authority | Denied |
| Admin | Platform permissions plus service object checks | Administrative permissions plus service checks | Subject to implemented owner/super-admin route rules | Admin only |
| Super admin | Platform override where explicitly implemented | Platform override where explicitly implemented | Creator dropdown eligibility override only; Phase 15 remains owner-scoped | Super admin |

Role permission is never the sole Creator gate. Active user-model/profile eligibility and object ownership remain mandatory.

## Financial calculation verification

### Units

- Monetary amounts are integer minor units.
- Rates are integer basis points from 0 through 10,000.
- Currencies are isolated by three-letter code.
- Analytics and UI formatting occur only after canonical integer calculations.

### Rule calculations

- Fixed deliverable, flat conversion, and milestone rules use the immutable flat amount.
- Percentage conversion uses exact integer floor arithmetic.
- Minimum source amount is checked before calculation.
- Maximum earning caps the calculated amount.
- Rule versions are immutable and content-hashed.

### Earning events

- Source earnings are campaign-idempotent.
- Manual adjustments require a nonzero signed amount and reason.
- Reversals are append-only negative events linked to one original event.
- Retries return the existing canonical event.

### Budget controls

- One budget exists per campaign and currency.
- Available, reserved, and committed movements use integer bucket events.
- Reservation and transition actions lock their canonical rows.
- Hard caps remain authoritative unless controlled overage is explicitly enabled.
- Released or reversed obligations restore balances through new ledger events rather than rewriting history.

### Payout boundary

Payout records consume eligible committed reservations and record state transitions. They do not call Stripe or another payment provider, access banking secrets, or represent a settled transfer without separate external confirmation.

## Database audit

The clean-database gate verifies:

- canonical migration chain plus Phase 6–15 SQL order
- all expected Creator Campaign tables
- customer-role permission backfill
- status-aware Phase 15 receipt unique index
- migration re-import/idempotency
- no MCP scope-count increase caused by the audit migration
- no duplicate onboarding record under idempotent creation
- no duplicate adjustment, reversal, or smoke receipt under retry

## Deployment order

1. Merge the audit PR into `integration-from-repair-20260628`.
2. Import:
   `database/20260723_creator_campaign_phases_1_15_production_audit_repair_v1.sql`
3. Deploy the latest integration ZIP.
4. Clear PHP opcode cache if the host does not do so automatically.
5. Run the production checks below.

## Required production checks after deployment

- Sign in as a customer account with an active Creator model/profile and confirm Discover, Applications, Deliverables, Tracking, Earnings, Payouts, and Disputes open under own-record scope.
- Confirm the same customer account is denied after the Creator assignment or profile is disabled.
- Open a Phase 15 selected campaign and verify each operational link remains scoped to that campaign.
- Run one percentage compensation example and compare the earning minor units with the exact basis-point calculation.
- Retry the same manual adjustment and reversal idempotency keys and confirm the original event is returned.
- Run a failed Phase 15 smoke test, repair the blocker without changing unrelated state, and confirm a passing receipt can coexist for the same state fingerprint.
- Change one product price, operator membership, campaign automatic-acceptance flag, or emergency-stop state and confirm the prior passing receipt becomes stale.
- Confirm onboarding activation does not publish the campaign and does not create an MCP run, action request, earning, payout, or payment-provider event.

## Residual operational risks

The remaining risks are operational rather than architectural:

- production data may contain old accounts without an active Creator model/profile and should remain denied until corrected
- existing campaigns may require merchants to complete deliverables, compensation, budgets, and tracking before Phase 15 can pass
- external payout settlement and tax compliance remain outside Creator Campaign Phase 8
- real social-network publication remains outside the platform's current execution boundary
- large-volume performance should be observed during the first production pilot and indexed from measured query plans rather than speculative counters
