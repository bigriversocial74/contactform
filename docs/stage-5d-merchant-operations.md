# Stage 5D Orders, PPPM Operations, Item Lifecycle, and Merchant Fulfillment Workspace

Stage 5D exposes the existing PPPM architecture through the shared merchant workspace.

## Included

- Merchant-scoped order and issuance-request visibility
- One operational row per individually stamped PPPM item
- Status, source, and search filters
- Item data sheets with source, invoice, line, value, recipient, expiration, and version snapshots
- Immutable lifecycle events
- Assignment, schedule, delivery, attempt, and claim history
- Merchant operational notes and case foundation
- Responsive merchant-shell pages

## Boundary

Payment records remain processor-neutral and are not used as PPPM identities. One order may produce many PPPM items, while each item preserves its own lifecycle and audit history.
