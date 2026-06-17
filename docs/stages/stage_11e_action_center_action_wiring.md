# Stage 11E — Action Center action wiring

Stage 11E connects the existing Action Center interface to the canonical Microgift ownership, claim, lifecycle projection, and durable delivery-event foundations.

## Implemented

- Send authorization through the authenticated Action Center item.
- Canonical PPPM owner transfer for gift sending.
- Customer claim through the Stage 9 claim and replay authorities.
- Stage 11D projection updates inside the owning claim/send transactions.
- Participant-scoped message authorization without gift lifecycle mutation.
- Existing Action Center modal submission wiring for send, claim, and message.
- Focused Stage 11E regression contracts.

## Preserved authorities

- `microgift_instances` remains the gift lifecycle source of truth.
- PPPM and entitlement ownership transfer remains canonical.
- Customer claim remains canonical in the Stage 9 claim service.
- `microgift_inbox_items` remains a Stage 11 read model.
- Message actions do not mutate gift ownership or lifecycle state.

## Deferred to Stage 11F

- reconciliation and repair tooling for historical projection drift;
- broader end-to-end fixtures and operational reconciliation reporting.
