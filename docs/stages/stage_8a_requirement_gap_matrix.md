# Stage 8A Requirement and Gap Matrix

## Classification key

- **Complete** — present and usable now.
- **Implemented early** — delivered in an earlier stage and carried forward.
- **Partial** — useful foundation exists but the Stage 8 contract is incomplete.
- **Misaligned** — current implementation conflicts with the intended contract.
- **Missing** — no reliable implementation exists.
- **Deferred** — intentionally belongs to a later stage.

| Stage 8 area | Status | Existing foundation | Stage 8 action |
|---|---|---|---|
| Permanent purchase identity | Implemented early | Commerce order items and paid-order PPPM issuance | Preserve as entitlement source; do not create new purchase records |
| Unit-level owned item identity | Implemented early | `pppm_items` | Use as the primary owned-unit reference |
| Customer owned/purchased/sent/received/redeemed lists | Implemented early | Stage 6B account item API | Extend read model with entitlement and asset access details |
| Product and version assets | Implemented early | Stage 4 product/version/media foundation | Reuse asset records and visibility rules |
| Merchant PPPM item operations | Implemented early | Stage 5D list/detail/note/lifecycle operations | Preserve merchant visibility and add entitlement status where authorized |
| Gift and claim ownership transitions | Partial | Gifts, claims, legacy mapping, PPPM lifecycle | Define a single entitlement transfer policy |
| Entitlement record | Missing | No canonical access-grant table identified | Add one canonical entitlement model linked to PPPM and asset identity |
| Entitlement idempotency | Missing | PPPM issuance is idempotent but access grants are not modeled | Require source-based unique keys |
| Entitlement status lifecycle | Missing | PPPM statuses exist but do not fully represent asset access | Define active, suspended, revoked, expired, and consumed states as needed |
| Refund access policy | Missing | Refund records and ledger postings exist | Define full and partial refund effects on access |
| Dispute access policy | Missing | Dispute records exist | Define temporary suspension and final resolution behavior |
| Transfer and claim access policy | Partial | Recipient and ownership fields exist | Define when access moves from sender to recipient |
| Expiration policy | Partial | PPPM and claim expiration fields exist | Define entitlement expiration source and enforcement |
| Protected download authorization | Missing | Media delivery helpers exist | Add entitlement check on every protected delivery request |
| Controlled delivery tokens | Partial | Signed media patterns exist | Standardize short-duration asset delivery grants |
| Download history | Missing | General events exist | Add access/download event records with actor, entitlement, asset, and time |
| Download limits | Missing | No canonical limit policy identified | Add optional policy fields only where product rules require them |
| Customer library detail | Partial | Account item list and detail foundations | Combine PPPM item, product snapshot, entitlement, assets, and access state |
| Merchant asset access reporting | Partial | Merchant product and PPPM views exist | Add authorized entitlement/access summaries without exposing customer private data |
| Administrative entitlement correction | Missing | Permission and audit patterns exist | Add permission-scoped suspend/revoke/restore operations |
| Audit and domain events | Partial | Existing audit/outbox/event helpers | Define entitlement and download event catalog |
| Future Demand signal capture | Deferred | Existing event and analytics foundation | Record source events now; scoring remains later stages |
| Wallet or ledger integration | Not applicable | Stage 7 money engine | Stage 8 must not mutate balances or create financial postings |

## Critical design decision

The platform already has a permanent owned-unit identity in `pppm_items`. Stage 8 should not create a competing owned-item model.

Recommended relationship:

`PPPM Item -> Entitlement -> Product Asset -> Authorized Access/Download Event`

The entitlement represents access rights. The PPPM item represents the purchased, gifted, assigned, or received unit. The product asset represents the protected content or file.

## Main gaps

1. No canonical entitlement/access-grant record.
2. No unified refund, dispute, transfer, claim, and expiration access policy.
3. No protected-download endpoint tied to entitlement checks.
4. No canonical access/download history.
5. Existing account library views do not yet combine PPPM ownership with asset-access state.

## Stage 8 readiness score

- Product and media foundation: 8.5/10
- Purchase and PPPM identity foundation: 9/10
- Customer library foundation: 6.5/10
- Gift/claim transfer foundation: 7/10
- Entitlement model: 1/10
- Protected download authorization: 2/10
- Refund/dispute access policy: 1/10
- Audit/event readiness: 7/10
- Overall Stage 8 readiness: 5.8/10

The score reflects strong early ownership and asset work, with the main Stage 8 access-control layer still missing.
