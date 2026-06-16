# Stage 9B Acceptance Checklist

- [x] Stage 9A planning is merged into `main`.
- [x] Canonical Microgift template table exists.
- [x] Immutable template version table exists.
- [x] Canonical gift instance table exists.
- [x] Existing gifts, PPPM items, and commerce order items remain compatibility references.
- [x] Secure credential table stores only hash, prefix, and last-four values.
- [x] Raw credentials are returned only once from issuance.
- [x] Issuance requires a source type, source reference, and idempotency key.
- [x] Commerce-backed issuance requires a verified paid order item owned by the issuing merchant.
- [x] Template and issuance writes require permissions and CSRF validation.
- [x] Instance reads are scoped to owner, recipient, or issuer.
- [x] Lifecycle events are append-only.
- [x] Stage 9B migration and smoke validation are included in consolidated CI.
- [x] Stage 9B PHPUnit contracts are present.
- [ ] PR Validation passes.
- [ ] Main Regression passes after merge.
