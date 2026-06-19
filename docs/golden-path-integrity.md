# Golden path integrity

This hardening phase validates checkout creation, payment capture, order fulfillment, PPPM ownership, Microgift issuance, purchaser claim, merchant-location completion, Action Center projection, ledger consistency, idempotency, and bounded recovery as one production lifecycle.

Purchaser-owned gifts can complete the claim step without user-entered credentials only when the authenticated user is already the canonical owner and recipient. The service creates and consumes an internal audit credential so the existing claim record model remains intact.

Location restrictions now support the canonical `selected_locations` policy generated during publishing and checkout fulfillment, while preserving legacy allow-list policy support.

`composer test-lifecycle-completion` runs the full checkout-through-completion behavior inside a rolled-back transaction. The protected `/admin/lifecycle-health.php` workspace scans bounded production relationships and offers two super-admin-only repairs: canonical paid-order fulfillment retry and Action Center reprojection.
