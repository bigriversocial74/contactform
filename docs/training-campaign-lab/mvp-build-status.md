# Training Campaign Lab MVP Build Status

## Branch

```text
local-quest-workspace
```

This branch remains separate from `main` and continues to act as the duplicate/fork workspace for the Loyalty Quest / Local Quest Rewards example.

## Completed build stages

```text
Phase 0: Documentation / planning package
Phase 1: Static Training Lab shell
Phase 2: SQL schema + seed + validation
Phase 3: SQL-backed campaign read model
Phase 4: Participant join + sequence view
Phase 5: Proof upload
Phase 6: Admin proof review
Phase 7: Action Receipts
Phase 8: Reward rule evaluation + reward issue records
Phase 9: Rewards / wallet display
Phase 10: Final validation file coverage
```

## MVP files now present

```text
examples/local-quest-rewards/training-lab.php
examples/local-quest-rewards/training-campaigns.php
examples/local-quest-rewards/training-campaign-detail.php
examples/local-quest-rewards/training-sequence.php
examples/local-quest-rewards/training-proof-upload.php
examples/local-quest-rewards/admin-training-review.php
examples/local-quest-rewards/admin-training-receipts.php
examples/local-quest-rewards/training-rewards.php
examples/local-quest-rewards/training-profile-wallet.php
examples/local-quest-rewards/training-storage.php
examples/local-quest-rewards/training-receipt-service.php
examples/local-quest-rewards/training-reward-service.php
examples/local-quest-rewards/training-campaign-data.php
examples/local-quest-rewards/assets/training-lab.css
examples/local-quest-rewards/assets/training-lab.js
examples/local-quest-rewards/database/training_campaign_lab.sql
examples/local-quest-rewards/database/training_campaign_lab_seed.sql
examples/local-quest-rewards/uploads/training-proof/.gitkeep
scripts/validate_training_campaign_lab.php
```

## Current MVP flow

The branch now has the demo-safe flow required for the first product vertical slice:

```text
Open Training Lab
Open Campaigns
Open Campaign Detail
Join Campaign
Open Sequence
Upload Proof
Admin Reviews Proof
Approval Creates Action Receipts
Approval Evaluates Reward Issue Records
Rewards Page Shows Reward Status
Profile / Wallet Shows Reward Status
Admin Receipts Shows Verified Action Ledger
```

## Demo-safe behavior

The current build intentionally uses demo-safe behavior:

```text
Training Lab runtime state is stored in data/training_lab_state.json
SQL is available as schema/seed foundation
Campaign read model uses SQL when available and PHP seed fallback when not
Proof files are stored under uploads/training-proof for local demo
Reward issues are recorded as status records
Real Microgifter reward issuing is not called yet
Existing Local Quest files are not replaced
```

## Test URLs

```text
/training-lab.php
/training-campaigns.php
/training-campaign-detail.php?campaign=5-day-movement-challenge
/training-sequence.php?campaign=5-day-movement-challenge
/training-proof-upload.php?campaign=5-day-movement-challenge&task=warm-up
/admin-training-review.php
/admin-training-receipts.php
/training-rewards.php?campaign=5-day-movement-challenge
/training-profile-wallet.php?campaign=5-day-movement-challenge
```

## Validation command

```bash
php scripts/validate_training_campaign_lab.php
```

## What should happen next

The next step is environment testing and hardening, not new product scope.

Recommended next checks:

```text
Run PHP syntax checks on all training-*.php files
Run validation script locally
Open each route in browser
Test join campaign
Submit text-note proof first
Submit file proof second
Approve proof in admin review
Confirm Action Receipt appears
Confirm Reward Issue appears
Confirm Profile / Wallet displays the reward issue
Confirm original Local Quest pages still load
```

## Hardening after local test

After the browser flow works locally:

```text
Move proof file access behind an authenticated file route
Decide whether to keep JSON runtime state for demo or switch all writes to SQL
Add stricter reviewer/admin permissions
Add participant account requirement if guest flow is not desired
Add retry/issue controls for real Microgifter rewards
Add audit log page when needed
```
