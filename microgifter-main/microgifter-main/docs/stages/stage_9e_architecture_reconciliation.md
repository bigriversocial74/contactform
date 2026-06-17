# Stage 9E — Architecture Reconciliation Before Stage 10

## Purpose

Stage 9E is a corrective reconciliation pass before the first production install and before Stage 10 begins. The code has not been deployed or migrated yet, so the priority is source-of-truth correctness on a clean initial install rather than backward-compatible data repair.

## Primary corrections

### PPPM ownership

PPPM is the canonical issued-unit and ownership source. Stage 9E adds `mg_pppm_transfer_owner_canonical()` and routes Microgift claims through it.

The canonical transfer sequence is:

1. lock the PPPM item,
2. calculate the old owner,
3. synchronize entitlements under the same transaction,
4. update `pppm_items.owner_user_id`,
5. append a PPPM ownership event and snapshot,
6. emit the canonical `pppm.owner_transferred` event,
7. update the Microgift instance owner.

This prevents divergence between Microgift ownership, PPPM ownership, and entitlement access.

### PPPM redemption

Stage 9E adds `mg_pppm_redeem()` and routes Microgift redemption through it. Microgift redemption no longer owns direct PPPM state mutation. PPPM redemption now owns the PPPM status update, event, and snapshot.

### Event catalog

Stage 9E adds `docs/contracts/event_catalog_stage1_9.yaml`, the first versioned event catalog for Stages 1–9. It defines canonical event names, domains, producers, required IDs, privacy boundaries, idempotency sources, and downstream consumers.

### API contract registry

Stage 9E adds `docs/contracts/api_contracts_stage1_9.yaml`, the first central API contract registry for high-risk Stage 1–9 endpoints. It identifies authentication, permission, CSRF, idempotency, source-of-truth, emitted events, and non-returnable sensitive fields.

## Deferred but now explicit

- A full generated OpenAPI contract can be produced from the YAML registry later.
- A linear migration ledger can be built before deployment if needed, but current staging remains acceptable for a clean initial install.
- Behavioral/concurrency tests should keep increasing coverage, but this pass establishes the critical ownership and redemption boundaries first.

## Stage 10 gate

Stage 10 should not begin until this pass is merged and green. The Stage 10A review should treat the Stage 9E contracts as authoritative Stage 1–9 baseline documents.
