# Training Campaign Lab Future File Manifest

## Purpose

This manifest lists files that may be created during a future implementation phase.

These files are not approved for creation yet. They are listed so future agents know where implementation should go after the user explicitly approves code.

## Current status

```text
Planning only.
Do not create these files yet.
```

## Stage 1: Static shell files

Purpose:

```text
Create a standalone Training Campaign Lab UI shell without database writes, proof uploads, or reward issuing.
```

Future files:

```text
examples/local-quest-rewards/training-lab.php
examples/local-quest-rewards/training-campaigns.php
examples/local-quest-rewards/training-campaign-data.php
examples/local-quest-rewards/assets/training-lab.css
examples/local-quest-rewards/assets/training-lab.js
```

Allowed behavior:

```text
Read static demo campaign data
Render dashboard shell
Render campaign library
Link back to original Local Quest app
Use existing app.php only for session/header/config context if needed
```

Not allowed in Stage 1:

```text
Database writes
File uploads
Review actions
Reward issue actions
Changes to protected Local Quest files
```

## Stage 2: SQL foundation files

Purpose:

```text
Create additive schema artifacts after the schema design has been approved.
```

Future files:

```text
examples/local-quest-rewards/database/training_campaign_lab.sql
examples/local-quest-rewards/database/training_campaign_lab_seed.sql
```

Rules:

```text
Additive only
Use training_* table names
Do not alter existing Local Quest tables
Do not drop existing Local Quest tables
Do not include production data
```

## Stage 3: Read model files

Purpose:

```text
Allow Training Lab pages to read campaigns, sequences, tasks, and reward rules from SQL with static fallback.
```

Future files:

```text
examples/local-quest-rewards/training-storage.php
examples/local-quest-rewards/training-campaign-detail.php
```

Future updates:

```text
examples/local-quest-rewards/training-lab.php
examples/local-quest-rewards/training-campaigns.php
```

Rules:

```text
Read only in this stage
No proof upload
No review actions
No reward issuing
```

## Stage 4: Participant join and sequence files

Purpose:

```text
Allow a user to join a campaign and view task progress.
```

Future files:

```text
examples/local-quest-rewards/training-sequence.php
```

Future updates:

```text
examples/local-quest-rewards/training-storage.php
examples/local-quest-rewards/training-campaign-detail.php
```

Rules:

```text
Create participant record only after sign-in/auth rules are decided
Prevent duplicate joins
Do not upload proof yet
Do not create rewards yet
```

## Stage 5: Proof upload files

Purpose:

```text
Allow a joined participant to submit proof for a task.
```

Future files:

```text
examples/local-quest-rewards/training-proof-upload.php
examples/local-quest-rewards/uploads/training-proof/.gitkeep
```

Future updates:

```text
examples/local-quest-rewards/training-storage.php
```

Rules:

```text
Validate file type
Validate file size
Use safe stored filename
Store proof metadata separately from display name
Proof is private by default
No public direct proof browsing
No reward creation on upload
```

## Stage 6: Admin review files

Purpose:

```text
Allow a reviewer/admin to approve, reject, or request resubmission.
```

Future files:

```text
examples/local-quest-rewards/admin-training-review.php
examples/local-quest-rewards/training-permissions.php
```

Future updates:

```text
examples/local-quest-rewards/training-storage.php
```

Rules:

```text
Reviewer/admin permission required
All POST actions require CSRF
Review decision writes event log
Approval does not directly issue reward
```

## Stage 7: Action Receipt files

Purpose:

```text
Create durable Action Receipt records from approved proof.
```

Future files:

```text
examples/local-quest-rewards/training-receipt-service.php
examples/local-quest-rewards/admin-training-receipts.php
```

Rules:

```text
Only approved proof creates receipts
One task completion receipt per approved task submission
One sequence completion receipt per completed sequence
Receipts cannot be manually created from the UI in MVP
```

## Stage 8: Reward issue files

Purpose:

```text
Evaluate reward rules and create reward issue records from Action Receipts.
```

Future files:

```text
examples/local-quest-rewards/training-reward-service.php
examples/local-quest-rewards/training-rewards.php
```

Rules:

```text
No reward issue without Action Receipt
Prevent duplicate reward issues
Use needs_linked_account status when account is not linked
Do not call real reward APIs until approved
```

## Stage 9: Profile and wallet files

Purpose:

```text
Display participant training status, receipts, reward issue statuses, and link to Local Quest wallet.
```

Future files:

```text
examples/local-quest-rewards/training-profile-wallet.php
```

Rules:

```text
Participant can view own status
Reviewer/admin can view review/admin pages
Do not replace existing wallet.php
Link to original wallet.php instead of editing it
```

## Stage 10: QA and validation files

Purpose:

```text
Add implementation validation only after implementation begins.
```

Future files:

```text
scripts/validate_training_campaign_lab.php
scripts/qa_training_campaign_lab_local.php
```

Rules:

```text
Do not create validation scripts while still in docs-only mode
Validation scripts must not require production credentials
Validation must confirm protected Local Quest files still exist
```

## Final note

This manifest is a plan, not approval. Do not create these files until implementation is explicitly approved.
