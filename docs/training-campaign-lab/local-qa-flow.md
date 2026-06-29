# Training Campaign Lab Local QA Flow

## Purpose

This is the local testing checklist for the Training Campaign Lab MVP branch after the remaining phases were built.

## Branch

```text
local-quest-workspace
```

Do not run this against `main`.

## Start local server

```bash
php -S 127.0.0.1:8090 -t examples/local-quest-rewards
```

## Run validation

```bash
php scripts/validate_training_campaign_lab.php
```

## Run local QA helper

```bash
php scripts/qa_training_campaign_lab_local.php
```

The local QA helper checks file presence, writable runtime folders, and attempts PHP lint checks when the PHP environment allows it.

## Manual route check

Open these routes in the browser:

```text
http://127.0.0.1:8090/training-lab.php
http://127.0.0.1:8090/training-campaigns.php
http://127.0.0.1:8090/training-campaign-detail.php?campaign=5-day-movement-challenge
http://127.0.0.1:8090/training-sequence.php?campaign=5-day-movement-challenge
http://127.0.0.1:8090/training-proof-upload.php?campaign=5-day-movement-challenge&task=warm-up
http://127.0.0.1:8090/admin-training-review.php
http://127.0.0.1:8090/admin-training-receipts.php
http://127.0.0.1:8090/training-rewards.php?campaign=5-day-movement-challenge
http://127.0.0.1:8090/training-profile-wallet.php?campaign=5-day-movement-challenge
```

## MVP browser flow

### 1. Open Campaigns

Open:

```text
/training-campaigns.php
```

Expected:

```text
Campaign cards appear
5-Day Movement Challenge appears
View Campaign opens detail page
```

### 2. Open Sequence and join

Open:

```text
/training-sequence.php?campaign=5-day-movement-challenge
```

Expected:

```text
Join Campaign button appears if not already joined
Clicking Join Campaign creates participant state
Sequence tasks appear
Warm-Up has Upload Proof CTA
```

### 3. Upload proof

Open:

```text
/training-proof-upload.php?campaign=5-day-movement-challenge&task=warm-up
```

Expected:

```text
Proof form appears
File upload or note submission works depending task type
Submission status becomes pending_review
```

### 4. Review proof

Open:

```text
/admin-training-review.php
```

Expected:

```text
Pending submission appears
Approve, Request Resubmission, and Reject actions appear
Approving proof changes status to approved
```

### 5. Confirm receipts

Open:

```text
/admin-training-receipts.php
```

Expected:

```text
Approved proof creates task completion Action Receipt
When all required tasks are approved, sequence completion receipt appears
Event timeline shows review and receipt activity
```

### 6. Confirm rewards

Open:

```text
/training-rewards.php?campaign=5-day-movement-challenge
```

Expected:

```text
Reward ladder appears
Reward issue records appear after sequence completion
If no linked account exists, status shows needs_linked_account
Real reward issuing is not called in this branch
```

### 7. Confirm profile wallet

Open:

```text
/training-profile-wallet.php?campaign=5-day-movement-challenge
```

Expected:

```text
Participant profile appears
Training receipts count appears
Reward issue records appear
Local Quest Wallet link remains available
```

## Original Local Quest regression check

Open:

```text
/index.php
/wallet.php
/quests.php
/admin.php
```

Expected:

```text
Original Local Quest pages still load
Training Lab did not replace the original app
```

## Known demo-safe behavior

```text
Runtime state is stored in examples/local-quest-rewards/data/training_lab_state.json
Proof files are stored in examples/local-quest-rewards/uploads/training-proof
SQL schema and seed exist, but write flow currently uses JSON runtime state for MVP demo safety
Real Microgifter rewards are not issued yet
Reward issue records are created for demo/status tracking
```

## Next hardening after local QA passes

```text
Move write flow from JSON runtime state to training_* SQL tables
Protect proof file downloads behind authenticated access
Add stricter admin/reviewer permission checks
Add CSRF tokens to Training Lab POST actions
Add SQL-backed audit log page
Add real Microgifter reward issue connector when config is ready
```
