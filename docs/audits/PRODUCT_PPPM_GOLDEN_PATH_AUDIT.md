# Product → PPPM Golden Path Audit

Status: **Audit in progress**  
Branch: `audit/product-pppm-golden-path`  
Scope: Simple Product, Greeting Card, checkout, per-unit PPPM issuance, Inbox, Sent, Resend, merchant-location claim, messaging, tipping, timestamps, and reconciliation.

## Intended golden path

```text
Merchant publishes product
→ customer purchases one or more quantities
→ each purchased unit receives one permanent PPPM ID
→ each PPPM unit links to one Microgift instance
→ buyer receives each unit in Inbox
→ sending transfers the same unit to the recipient automatically
→ sender retains timestamped Sent history
→ recipient receives the unit automatically in Inbox
→ merchant enters the eligible location claim code into the voucher
→ the same unit moves to Claimed
→ post-claim tip and message activity remains linked to that PPPM ID
```

There is no recipient acceptance step between Inbox delivery and merchant claim.

## Executable audit coverage

The audit command is:

```bash
composer audit-product-pppm-golden-path
```

It runs inside a database transaction and rolls back all fixtures. It creates:

- one Simple Product line with quantity `2`;
- one Greeting Card line with quantity `3`;
- one paid order with five purchased units;
- five PPPM IDs;
- five Microgift instances;
- buyer Inbox projections;
- transfer, second-transfer, closed-transfer, merchant-claim, location-policy, messaging, and timestamp probes.

The audit is intentionally non-gating while findings are being reviewed. Execution errors fail CI; detected contract findings are emitted as structured JSON.

## Confirmed code-review findings

### Critical — Canonical transfer lacks actor authorization

`mg_pppm_transfer_owner_canonical()` accepts an actor ID but does not require that actor to be the current owner or another authorized transfer authority. Any caller that reaches the helper can request an ownership change.

Required correction:

- require the current owner or an explicit privileged authority;
- validate the destination user;
- reject self-conflicting and stale transfer requests;
- bind idempotency to the complete transfer fingerprint.

### Critical — Closed PPPM items remain transferable

The canonical transfer helper does not reject redeemed, cancelled, revoked, expired, or replaced units.

Required correction:

- enforce the transferable state set inside the canonical helper;
- do not rely on every endpoint to duplicate lifecycle guards.

### Critical — Merchant claim still requires an extra recipient claim

Purchased gifts are issued with recipient policy `purchaser`, which creates no recipient claim credential. The merchant-location redemption path currently accepts only `claimed` or `redeemable` Microgift states. A purchased or delivered gift therefore cannot move directly from recipient Inbox to merchant claim.

This conflicts with the intended flow:

```text
received automatically → merchant enters location claim code → claimed
```

Required correction:

- remove the recipient-credential claim prerequisite for purchased and sent vouchers;
- let the atomic merchant-location claim consume an owned `issued` or `delivered` voucher;
- use the merchant location claim code as the claim authority;
- record one atomic claimed transaction and timestamp.

### Critical — Published location policy and claim validator use different schemas

Product publication writes:

```json
{"mode":"selected_locations","location_ids":["..."]}
```

The lifecycle validator reads `allow_list` and `allowed_locations`. Under the current logic, an unlisted non-empty location can pass a `selected_locations` policy.

Required correction:

- define one canonical location-policy schema;
- validate the merchant location ID against the published product-version/location association;
- reject missing, inactive, foreign, and unlisted locations.

### High — Current recipient is not updated after subsequent transfers

PPPM owner transfer uses:

```sql
recipient_user_id = COALESCE(recipient_user_id, ?)
```

The first recipient remains stored after a later recipient becomes owner.

Required correction:

- preserve original purchaser and issuer separately;
- set `current_owner_user_id` and `current_recipient_user_id` on every transfer;
- keep prior recipients only in immutable transfer history.

### High — PPPM state does not advance during send/delivery

Ownership changes while the PPPM unit remains `available`. The permanent PPPM record therefore does not represent the Sent/Delivered lifecycle that the Action Center displays.

Required correction:

- define canonical status transitions for purchased, sent, delivered, and claimed;
- update PPPM, Microgift, transfer history, and projections in one transaction.

### High — Original issuer can be overwritten during send

The Action Center send path updates `issuer_user_id` to the sender. That loses the immutable merchant issuer and changes message participants.

Required correction:

- never overwrite original issuer;
- store each sender in immutable transfer events;
- distinguish original merchant, purchaser, current sender, and current owner.

### High — PPPM and Microgift can disagree about issuer

Paid-order PPPM issuance records the buyer/actor as issuer while the Microgift definition is merchant-owned and the Microgift instance is issued by the merchant.

Required correction:

- define original issuer consistently;
- normally use merchant as issuer and buyer as initial owner for a purchased merchant voucher.

### High — Post-claim message authority does not explicitly resolve the merchant

Message participants are derived from mutable `issuer_user_id`, `owner_user_id`, and `recipient_user_id`. The selling merchant and redemption location are not resolved as explicit post-claim participants.

Required correction:

- preserve the merchant issuer;
- define whether post-claim messages go to the sender, merchant, location, or a selected participant;
- enforce that policy from the PPPM/claim record.

### Medium — Customer purchase creates merchant Sent-tab projections

Checkout currently projects merchant-owned issuance as Sent history immediately after sale. This may mix commerce sales with user-initiated gifting.

Decision required:

- merchant sale history may belong in merchant orders/PPPM operations;
- Sent should represent explicit user transfer activity unless the product definition says otherwise.

### Medium — Messaging is available before merchant claim

Messaging authority currently checks participant membership but not lifecycle state. If messaging is intended to become available only after claim, the backend must enforce that policy.

## Areas expected to pass

The existing architecture appears to support these contracts, subject to the executable audit:

- one PPPM issuance request per invoice line;
- one PPPM item per line quantity using `unit_sequence`;
- one Microgift instance per PPPM item;
- idempotent checkout capture and fulfillment replay;
- buyer Inbox projection per unit;
- immutable send and resend delivery-event timestamps;
- resend without creating a new PPPM ID or changing ownership;
- tipping gated to a completed merchant redemption;
- message and tip records with creation timestamps.

## Recommended repair order

1. Canonical transfer authorization, lifecycle guards, recipient updates, and immutable issuer.
2. Direct merchant-location claim from received/delivered ownership.
3. Canonical product-location policy enforcement.
4. Unified PPPM/Microgift/projection lifecycle transaction.
5. Post-claim message participant policy.
6. Merchant Sent-versus-sales projection decision.
7. Permanent PPPM ID length/collision retry hardening.
8. Full reconciliation validator promoted from non-gating audit to release gate.
