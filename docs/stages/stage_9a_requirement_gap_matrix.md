# Stage 9A — Requirement and Gap Matrix

## Classification key

- **Complete** — present and usable now.
- **Implemented early** — delivered before Stage 9 and carried forward.
- **Partial** — useful foundation exists but the Stage 9 contract is incomplete.
- **Misaligned** — current behavior conflicts with the intended canonical contract.
- **Missing** — no reliable implementation exists.
- **Deferred** — intentionally belongs to a later stage.

| Stage 9 area | Status | Existing foundation | Adapted Stage 9 action |
|---|---|---|---|
| User, merchant, creator, organization, and enterprise ownership | Implemented early | Identity, profiles, roles, permissions | Use canonical owner IDs and explicit owner type |
| Product and offer references | Implemented early | Products, immutable versions, assets, commerce snapshots | Reference published products/versions and snapshot instance terms |
| Reusable Microgift template | Missing | Existing gifts are primarily issued gift records | Add one canonical template contract |
| Versioned template terms | Missing | Product versions and PPPM snapshots demonstrate the pattern | Add immutable template versions |
| Gift instance | Partial | `gifts`, PPPM items, order items, claims | Define canonical instance identity and compatibility mapping |
| Gift-instance immutable snapshot | Partial | PPPM and commerce snapshots exist | Snapshot template/version, value, terms, owner, location, recipient policy, and expiration |
| Secure redeem-code generation | Partial | Claim code last-four and attempt controls exist | Standardize cryptographic generation and hash-only storage |
| Raw-code persistence prohibition | Missing as a formal contract | Security patterns exist | Explicitly prohibit raw code storage and logging |
| Redeem-code lookup | Partial | Existing claim-code flow | Add indexed prefix/last-four plus constant-time hash verification |
| Failed-attempt lockout | Implemented early | Gift claim failed attempts and lock timestamps | Reuse/adapt policy and rate limits |
| Recipient assignment | Partial | Gifts, PPPM recipient fields, claims | Define named, external, open-claim, and user-bound policies |
| Claim lifecycle | Partial | Existing claim records and PPPM lifecycle | Consolidate under gift-instance orchestration |
| Redemption lifecycle | Partial | Gift and PPPM redeemed states | Add transaction-safe canonical redemption service |
| Idempotent issuance | Partial | PPPM and commerce idempotency exist | Require source keys for every instance issuance |
| Idempotent redemption | Missing as unified contract | Existing claim behavior is fragmented | Add unique redemption source/idempotency records |
| Expiration | Partial | Gifts, claims, PPPM, entitlement expiration fields | Define source-of-truth precedence and sweeper contract |
| Cancellation/revocation | Partial | Gift statuses and entitlement policy exist | Define pre-claim cancellation and post-claim revocation rules |
| Replacement/reissue | Missing | Entitlement review patterns exist | Add linked replacement without deleting history |
| Commerce-funded gifts | Implemented early | Cart, orders, payments, receipts, PPPM issuance | Orchestrate instance creation from verified paid order items |
| Non-commerce/admin issuance | Partial | PPPM source/issuance and admin patterns | Add permission-scoped audited issuance source |
| PPPM integration | Implemented early | Permanent unit identity and ownership | Link instances to PPPM; never replace PPPM |
| Entitlement integration | Implemented early | Stage 8 grants and owner sync | Trigger only through canonical PPPM ownership/access services |
| Wallet/ledger integration | Not applicable for direct Stage 9 mutation | Stage 7 money engine | Observe verified funding; do not create balance logic |
| Location restrictions | Partial | Location foundations exist | Reference canonical locations and enforce at redemption |
| Merchant operations | Partial | Merchant gifts, PPPM, product, order APIs | Add template/instance list and detail with privacy controls |
| Customer library | Implemented early | Sent, received, redeemed, owned views | Extend existing account read models with template/instance status |
| Enterprise/workplace programs | Partial | Workplace rewards direction and organization ownership | Support owner/program references; defer advanced program automation |
| Agent-driven issuance | Deferred | Agent workspace and saved agents exist | Expose authorized idempotent APIs/events; schedule/execution later |
| Future Demand event capture | Partial | Event, engagement, distribution, demand foundations | Emit reliable template/instance lifecycle events only |
| Predictive Future Demand scoring | Deferred | Conceptual profile metrics | Later intelligence stages calculate scores |
| Audit and domain events | Implemented early | Audit/event/outbox patterns | Define complete Microgift event catalog |
| Security and abuse controls | Partial | CSRF, permissions, rate limits, claim attempt controls | Add redeem-specific throttling, privacy, hashing, locking, and tests |
| Migration from existing gifts | Missing | Existing gift and legacy mapping records | Create explicit compatibility/migration plan before new production use |

## Critical architectural decision

Stage 9 should not treat `gifts`, `gift_claims`, `pppm_items`, and entitlements as interchangeable.

Recommended relationship:

`Microgift Template -> Template Version -> Gift Instance -> Claim/Redemption -> PPPM Item -> Entitlement`

Commerce, agent, workplace, administrator, or other authorized sources may request issuance, but the instance service remains canonical.

## Highest-priority gaps

1. Canonical reusable template and immutable version model.
2. Canonical gift-instance contract and mapping to existing gifts/PPPM.
3. Hash-only redeem-code service with safe lookup and lockout.
4. Idempotent transaction-safe redemption.
5. Explicit expiration, cancellation, revocation, and replacement policy.
6. Location and recipient eligibility enforcement.
7. Compatibility plan for existing gift and claim records.
8. Unified merchant/customer read models and event catalog.

## Stage 9 readiness score

- Identity and permissions: 9/10
- Product and commerce dependencies: 9/10
- PPPM and entitlement dependencies: 9/10
- Existing gift/claim foundation: 6.5/10
- Canonical template model: 1/10
- Canonical instance orchestration: 4/10
- Secure redeem-code contract: 4.5/10
- Redemption idempotency and locking: 4/10
- Location and recipient policy: 5/10
- Analytics/event readiness: 8/10
- Overall Stage 9 readiness: 6.0/10

The readiness score is intentionally lower than the completed Stage 8 score because the central Microgift template/version/instance orchestration still needs to be built. The supporting foundations are strong.
