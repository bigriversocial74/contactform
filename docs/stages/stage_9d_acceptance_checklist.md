# Stage 9D Acceptance Checklist

- [x] Customer Microgift library exposes canonical owned, sent, received, claimed, and redeemed scopes.
- [x] Customer records include linked PPPM, entitlement, claim, and redemption status.
- [x] Merchant operations are permission and owner scoped.
- [x] Merchant summaries include lifecycle, value, customer, and location facts.
- [x] Administrator inspection combines the full immutable timeline.
- [x] Review queue writes require permission, CSRF, and audit records.
- [x] Legacy reconciliation creates review items without deleting legacy records.
- [x] Ownership mismatches are surfaced for controlled review.
- [x] Daily metrics capture source facts without calculating Future Demand scores.
- [x] No operational endpoint exposes raw credentials.
- [x] Stage 9D migration, smoke, aggregation, reconciliation, and PHPUnit coverage exist.
- [x] Stage 10 handoff preserves the Stage 9 contracts.
- [ ] PR Validation passes.
- [ ] Main Regression passes after merge.
