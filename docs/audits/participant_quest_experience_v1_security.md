# Participant Quest Security Authority

- Microgifter authentication owns participant identity.
- Merchant permissions scope review and signed-code generation.
- Quest audience rules are enforced before enrollment.
- Completion codes are stored as hashes.
- Signed QR codes are campaign-bound, expiring, HMAC-authenticated, and single-use.
- Location verification enforces radius and accuracy.
- Purchase and external references are deduplicated per campaign.
- Reward issuance remains idempotent and uses the existing wallet/PPPM authority.
