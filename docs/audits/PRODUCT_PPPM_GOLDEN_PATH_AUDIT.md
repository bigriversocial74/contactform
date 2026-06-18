# Product → PPPM Golden Path Audit

Status: **Completed**  
Branch: `audit/product-pppm-golden-path`

## Results

The clean-database audit completed successfully and rolled back all fixtures.

```text
17 executable checks
5 passed
12 confirmed behavior findings
  5 critical
  5 high
  2 medium
```

A separate product-routing contract review found one additional critical issue. Total audit inventory:

```text
13 findings
  6 critical
  5 high
  2 medium
```

The complete machine-readable report is uploaded by CI as `product-pppm-golden-path-audit`.

## Intended flow

```text
Publish product
→ purchase one or more quantities
→ one permanent PPPM ID per unit
→ buyer Inbox
→ send to recipient
→ sender Sent history
→ automatic recipient Inbox delivery
→ merchant enters eligible location claim code
→ Claimed
→ post-claim tip/message history
```

There is no recipient acceptance step between Inbox delivery and merchant claim.

## Verified strengths

- Two invoice lines with quantities `2` and `3` create exactly five PPPM items.
- Each line has a complete unique `unit_sequence` set.
- Each PPPM item links one-to-one to a Microgift instance.
- All five purchased units appear independently in the buyer Inbox.
- All five units record PPPM and Microgift issuance timestamps.
- Clean migrations, application startup, behavior validators, and PHPUnit remain green.
- Existing resend validation continues to prove no duplicate unit, ownership change, or resend timestamp event.

## Critical findings

### Canonical transfer lacks actor authorization

An unrelated user successfully invoked the canonical transfer helper against a buyer-owned item.

Repair: require the current owner or an explicit privileged authority inside the canonical helper, validate the destination, and bind idempotency to the full transfer request.

### Closed items remain transferable

A PPPM item could change owners after it was marked `redeemed`.

Repair: reject redeemed, cancelled, revoked, expired, and replaced units inside the canonical transfer authority.

### Merchant claim requires an extra recipient-claim stage

Purchased gifts issue no recipient claim credential, while merchant claim accepts only `claimed` or `redeemable`. Direct merchant claim from `issued` failed with `Microgift is not in an eligible state.`

Repair: allow an owned `issued` or `delivered` gift to be atomically claimed by an authorized merchant location code. Do not add a customer acceptance step.

### Purchased gifts have no bridge into the current claim prerequisite

The audit confirmed zero active recipient claim credentials for purchased gifts.

Repair: remove the obsolete recipient credential prerequisite; ownership plus merchant-location authority should be sufficient.

### Product-location policy is not enforced correctly

Publication writes `selected_locations` and `location_ids`, while redemption reads `allow_list` and `allowed_locations`. An unlisted location can pass.

Repair: use one location-policy schema and validate against the product-version/location association.

### Product URLs are ambiguous across merchants

The database allows the same slug for different merchants, but the public route is only `product.php?p=slug` and the API queries by slug alone with `LIMIT 1`.

Repair: use merchant + product slug, or a globally unique public product identifier, everywhere.

## High findings

### Current recipient becomes stale after a second transfer

After sending Recipient A → Recipient B, owner became B while `recipient_user_id` stayed A.

### PPPM status remains `available` after send/delivery

Ownership changes without advancing the permanent PPPM lifecycle state.

### Sending overwrites the original issuer

The send endpoint updates `issuer_user_id` to the sender, losing the immutable merchant issuer.

### PPPM and Microgift disagree about original issuer

All five audited purchased units had different issuer authorities between PPPM and Microgift.

### Post-claim messaging does not explicitly resolve the merchant

Participants are derived only from mutable issuer/owner/recipient fields, not the selling merchant or redemption location.

## Medium findings

### Customer purchases populate the merchant Sent tab

Five purchased units immediately created five merchant Sent rows, mixing sales with explicit user sends.

### Messaging is available before merchant claim

The audit successfully sent a message before claim because messaging checks participants but not lifecycle state.

## Repair order

1. Transfer authorization, closed-state guards, recipient updates, and immutable issuer.
2. Direct merchant-location claim from received/delivered ownership.
3. Canonical product-location policy enforcement.
4. Globally unambiguous product routing.
5. Unified PPPM/Microgift/projection lifecycle transaction.
6. Post-claim messaging policy.
7. Merchant Sent-versus-sales projection decision.
8. PPPM ID length and collision-retry hardening.
9. Promote this audit from non-gating evidence to a release gate after repairs.
