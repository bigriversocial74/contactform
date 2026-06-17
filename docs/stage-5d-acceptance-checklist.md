# Stage 5D Acceptance Checklist

- Merchant order and issuance-request views are scoped by `merchant_user_id`.
- Every PPPM unit appears as an independent item with its permanent ID.
- Status, source, order reference, and text filters work.
- Item details expose immutable snapshots, lifecycle events, assignment history, schedules, delivery attempts, and claim state.
- Merchant notes require permission and CSRF protection.
- Exceptions remain auditable without mutating historical events.
- Payments remain separate from PPPM identity and lifecycle.
- Operations pages reuse the Stage 5 merchant shell on desktop and mobile.
