# Participant Quest Experience v1 Fixes

Implemented fixes:

1. Enforced public, invite-only, customer, loyalty-member, new-customer, campaign-contact, and geographic-radius audiences.
2. Standardized verification identifiers and added hashed invite, static QR, staff, and event codes.
3. Added campaign-bound, expiring, single-use signed QR codes with HMAC validation and replay storage.
4. Added precise-location accuracy and radius checks.
5. Added duplicate reference, daily completion, repeat cooldown, per-user reward, quantity, and budget limits.
6. Added idempotent Microgifter wallet issuance and PPPM bridge reuse.
7. Added participant enrollment, guided completion, QR camera scanning, review status, evidence history, and My Loyalty Quests.
8. Added merchant evidence search, queue metrics, approve/reject decisions, review notes, audit events, and reward issuance.
9. Added merchant signed completion-code generation.
10. Registered shared identity and participant migrations in the canonical full-upgrade manifest.
11. Added customer and merchant navigation links.
12. Added PHP 8.2/8.3 validation and canonical migration coverage.
