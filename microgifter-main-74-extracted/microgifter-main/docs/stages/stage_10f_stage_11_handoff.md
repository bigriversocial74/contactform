# Stage 10F Closeout and Stage 11 Handoff

## Product decision

The customer Action Center exposes exactly three primary folders:

1. **INBOX** — received and redeemable gifts currently owned by the user.
2. **SENT** — gifts transferred by the user to another recipient.
3. **CLAIMED** — gifts successfully redeemed after merchant-location claim-code verification.

`claimable` is a compatibility term for `redeemable`. `claimed` is not a separate final customer folder; merchant-completed redemption is presented as CLAIMED.

## Supported customer flow

1. A user purchases a gift or receives a free/authorized gift.
2. The gift appears in INBOX for the current owner.
3. The owner may keep it for personal redemption or send it to another user.
4. Sending creates a SENT history row for the sender and an INBOX row for the recipient while PPPM ownership remains canonical.
5. The recipient or current owner presents the gift to an authorized merchant location.
6. The merchant enters and verifies the location claim code.
7. The atomic redemption transaction completes Microgift, PPPM, claim-code usage, Action Center, event, and outbox effects.
8. The redeemed gift appears in CLAIMED for the redeemer.

## Stage 10F architecture corrections

- One canonical claim orchestration entry point.
- Database-configured rate policies resolved within the canonical operation.
- Typed lifecycle errors with stable result codes.
- Explicit verification of commerce-backed and approved non-commerce issuance sources.
- Operational outbox insertion before transaction commit.
- Immutable attempt audit rows separated from expiring request/security metadata.
- Retention deletes expired security envelopes instead of rewriting audit rows.
- Durable failed-attempt logging after domain rollback.
- Ordered Stage 10 migration execution and generated full-upgrade artifacts.
- Runtime transaction checks in CI.

## Stage 11A requirements

Stage 11A should implement the complete Action Center service without creating another gift engine:

- INBOX, SENT, and CLAIMED list endpoints;
- stable cursor pagination;
- user-scoped detail endpoint;
- per-folder total and unread counters;
- mark read/unread and archive/restore display operations;
- read-model creation on purchase, free pickup, delivery, send, resend, replacement, redemption, expiration, and revocation;
- reconciliation job that rebuilds missing read rows from canonical Microgift and PPPM history;
- privacy-safe sender, recipient, merchant, and location display fields;
- tests proving sender history cannot redeem after ownership transfer;
- tests proving only merchant redemption moves the current owner's item to CLAIMED.

## UI boundary

The frontend must use the approved shared application template. Folder labels and display state are presentation concerns; ownership and redemption decisions always come from canonical backend services.
