# Stage 9A — Official Plan Review

## Official source

The official Stage 9 source is `docs/stages/Microgifter_Stage_9_Backend_Implementation_Plan_v1.docx`.

Stage 9 is the **Microgift Engine** stage. Its core plan centers on reusable microgift templates, issued gift instances, secure redeem-code handling, lifecycle rules, redemption, and the integration boundaries that connect gifts to the platform foundations completed in Stages 1–8.

## Stage 9 purpose

Stage 9 should create the canonical reusable gift-program and issued-gift engine without duplicating:

- products or product versions
- commerce orders or payments
- PPPM issued-unit ownership
- entitlements or protected asset access
- claims and existing gift history
- wallet or ledger balances
- merchant profiles, locations, agents, or Future Demand analytics

## Core official requirements

The Stage 9 plan requires these functional areas:

1. Microgift templates owned by an authorized merchant, creator, organization, enterprise, or user.
2. Versioned template terms and value rules.
3. Gift instances created from an immutable template/version snapshot.
4. Secure redeem codes stored only as hashes.
5. Human-safe code lookup using non-sensitive prefixes or last-four values.
6. Explicit gift lifecycle states and append-only events.
7. Recipient assignment and claim/redeem rules.
8. Expiration, cancellation, revocation, and replacement handling.
9. Idempotent issuance and redemption.
10. Server-authoritative eligibility, balance, ownership, and entitlement checks.
11. Merchant and administrator visibility with privacy boundaries.
12. Audit records and domain events for high-risk transitions.
13. Migration, smoke, security, and regression coverage.

## Adaptation required after Stages 1–8

The original Stage 9 plan predates substantial early implementation. Stage 9 must therefore adapt to the current repository rather than rebuilding gifts from scratch.

The canonical relationship should be:

`Microgift Template -> Template Version -> Gift Instance -> Claim/Redeem Event -> PPPM Ownership/Entitlement/Commerce References`

A gift instance represents the transferable or redeemable gift contract. PPPM remains the issued-unit ownership identity. Entitlements remain protected digital access rights. Commerce remains the purchase source. The grouped ledger remains the internal money source.

## Non-negotiable boundaries

- Never store a raw redeem code after issuance.
- Never trust a client-provided value, merchant, product, recipient, ownership, or redemption status.
- Never create a second payment, order, PPPM, entitlement, asset, or wallet system.
- Never mutate historical gift, claim, redemption, ownership, entitlement, or financial events.
- Never expose recipient private data to unauthorized merchants or users.
- Never allow a redeem operation without transaction locking and idempotency.

## Stage 9A conclusion

The official plan remains valid, but Stage 9 is now primarily a **consolidation and canonical-contract stage**. Existing gifts, claims, PPPM, commerce, entitlements, profiles, locations, agents, and analytics foundations must be reconciled into one Microgift Engine before new schema or APIs are added.
