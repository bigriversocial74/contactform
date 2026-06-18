# Product → PPPM Golden Path Audit

Status: **Completed**  
Branch: `audit/product-pppm-golden-path`  
Scope: Simple Product, Greeting Card, checkout, per-unit PPPM issuance, Inbox, Sent, Resend, merchant-location claim, messaging, tipping, timestamps, product routing, and reconciliation.

## Results

The clean-database executable audit completed successfully and rolled back all fixtures.

```text
17 executable checks
5 passed
12 confirmed behavior findings
  5 critical
  5 high
  2 medium
  0 low
```

A separate product-routing contract review identified one additional critical finding. The complete audit inventory is therefore:

```text
13 findings total
  6 critical
  5 high
  2 medium
```

The CI artifact is named `product-pppm-golden-path-audit` and contains `product_pppm_golden_path_audit.json`.

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

## Verified strengths

The audit proved:

- two invoice lines with quantities `2` and `3` issue exactly five PPPM items;
- each invoice line has a complete, unique `unit_sequence` set;
- each purchased unit has one permanent PPPM ID and one Microgift instance;
- all five purchased units appear independently in the buyer Inbox;
- every purchased unit records both PPPM and Microgift issuance timestamps;
- the full clean migration, application startup, behavior validators, and PHPUnit suite remain green;
- existing resend validation continues to prove no duplicate PPPM ID, Microgift instance, ownership transfer, or timestamp event.

## Confirmed findings

### Critical — Canonical transfer lacks actor authorization

`mg_pppm_transfer_owner_canonical()` accepts an actor ID but does not require that actor to be the current owner or another authorized transfer authority. The audit successfully transferred a buyer-owned unit when invoked by an unrelated user.

Required correction:

- require the current owner or an explicit privileged authority;
- validate the destination user;
- reject self-conflicting and stale transfer requests;
- bind idempotency to the complete transfer fingerprint.

### Critical — Closed PPPM items remain transferable

The audit successfully changed ownership of a PPPM item after setting it to `redeemed`.

Required correction:

- enforce the transferable state set inside the canonical helper;
- reject redeemed, cancelled, revoked, expired, and replaced units;
- do not rely on every endpoint to duplicate lifecycle guards.

### Critical — Merchant claim still requires an extra recipient claim

Purchased gifts are issued with recipient policy `purchaser`, which creates no recipient claim credential. The merchant-location redemption path accepts only `claimed` or `redeemable` Microgift states. The audit's direct merchant claim failed from `issued` with `Microgift is not in an eligible state.`

This conflicts with the intended flow:

```text
received automatically → merchant enters location claim code → claimed
```

Required correction:

- remove the recipient-credential claim prerequisite for purchased and sent vouchers;
- let the atomic merchant-location claim consume an owned `issued` or `delivered` voucher;
- use the merchant location claim code as the claim authority;
- record one atomic claimed transaction and timestamp.

### Critical — Purchased gifts have no bridge into the existing claim prerequisite

The audit confirmed zero active recipient claim credentials for a purchased gift while the merchant path still requires a prior claimed/redeemable state.

Required correction:

- eliminate the obsolete recipient claim stage for this product flow rather than generating another customer credential;
- make ownership plus merchant-location authority the complete claim contract.

### Critical — Published location policy and claim validator use different schemas

Product publication writes:

```json
{"mode":"selected_locations","location_ids":["..."]}
```

The lifecycle validator reads `allow_list` and `allowed_locations`. The audit confirmed an unlisted non-empty location is treated as allowed under `selected_locations`.

Required correction:

- define one canonical location-policy schema;
- validate the merchant location ID against the published product-version/location association;
- reject missing, inactive, foreign, and unlisted locations.

### Critical — Product URL slugs are ambiguous across merchants

The catalog database guarantees only `(merchant_user_id, slug)` uniqueness. The public page accepts only `product.php?p=slug`, and the public product API queries only `WHERE cp.slug = ? ... LIMIT 1`.

Two merchants can therefore publish the same slug, while the public URL has no merchant identifier to disambiguate them. The result may resolve the wrong merchant's product and purchase version.

Required correction:

Choose one authoritative route contract:

```text
/product.php?m=merchant-slug&p=product-slug
```

or a globally unique public product slug/ID. The API, storefront links, feed links, discovery results, and canonical URLs must all use the same contract.

### High — Current recipient is not updated after subsequent transfers

The audit sent one PPPM unit to Recipient A and then Recipient B. The owner became Recipient B while `recipient_user_id` remained Recipient A because transfer uses:

```sql
recipient_user_id = COALESCE(recipient_user_id, ?)
```

Required correction:

- preserve original purchaser and issuer separately;
- set current owner and current recipient on every transfer;
- keep prior recipients only in immutable transfer history.

### High — PPPM state does not advance during send/delivery

After two transfers, the audited PPPM unit remained `available`. The permanent PPPM record therefore does not represent the Sent/Delivered lifecycle shown by the Action Center.

Required correction:

- define canonical status transitions for purchased, sent, delivered, and claimed;
- update PPPM, Microgift, transfer history, and projections in one transaction.

### High — Original issuer can be overwritten during send

The Action Center send path updates `issuer_user_id` to the sender. That loses the immutable merchant issuer and changes message participants.

Required correction:

- never overwrite original issuer;
- store each sender in immutable transfer events;
- distinguish original merchant, purchaser, current sender, and current owner.

### High — PPPM and Microgift disagree about issuer

All five audited units had different PPPM and Microgift issuer authorities. Paid-order PPPM issuance records the buyer/actor as issuer while the merchant-owned Microgift is issued by the merchant.

Required correction:

- define original issuer consistently;
- normally use merchant as issuer and buyer as initial owner for a purchased merchant voucher.

### High — Post-claim message authority does not explicitly resolve the merchant

Message participants are derived only from mutable `issuer_user_id`, `owner_user_id`, and `recipient_user_id`. The selling merchant and redemption location are not resolved as explicit post-claim participants.

Required correction:

- preserve the merchant issuer;
- define whether post-claim messages go to the sender, merchant, location, or a selected participant;
- enforce that policy from the PPPM/claim record.

### Medium — Customer purchase creates merchant Sent-tab projections

The audit found five merchant Sent rows immediately after the customer purchased five units. This mixes commerce sales with user-initiated gifting.

Decision required:

- merchant sale history should normally live in merchant orders/PPPM operations;
- Sent should represent explicit user transfer activity unless the product definition says otherwise.

### Medium — Messaging is available before merchant claim

The audit successfully sent a Microgift message before merchant claim. Messaging authority checks participant membership but not lifecycle state.

Required correction if messaging is intended to begin after claim:

- enforce the lifecycle gate in the backend;
- keep message timestamps and PPPM linkage unchanged.

## Recommended repair order

1. Canonical transfer authorization, lifecycle guards, recipient updates, and immutable issuer.
2. Direct merchant-location claim from received/delivered ownership.
3. Canonical product-location policy enforcement.
4. Globally unambiguous product routing.
5. Unified PPPM/Microgift/projection lifecycle transaction.
6. Post-claim message participant policy.
7. Merchant Sent-versus-sales projection decision.
8. Permanent PPPM ID length/collision retry hardening.
9. Promote the reconciliation audit from non-gating evidence to a release gate after repairs.
