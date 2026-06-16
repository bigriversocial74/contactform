# Stage 9E-3 Acceptance Checklist

- [x] Early Stage 1 install is treated as existing, not blank.
- [x] Existing accounts/users are explicitly preserved.
- [x] Zip upload and extract workflow is documented.
- [x] Preflight script checks base auth/account tables.
- [x] Upgrade manifest prints the additive stage command sequence.
- [x] Smoke script verifies Stage 2–9 core tables, columns, permissions, and contracts.
- [x] CI runs Stage 9E-3 preflight, manifest, and smoke scripts.
- [x] PHPUnit contracts protect the early-install upgrade assumptions.
- [x] Stage 10 gate requires live upgrade smoke and existing-login verification.
- [ ] PR Validation passes.
- [ ] Main Regression passes after merge.
