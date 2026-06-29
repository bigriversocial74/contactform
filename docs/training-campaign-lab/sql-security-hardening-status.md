# Training Campaign Lab SQL and Security Hardening Status

## Branch

```text
local-quest-workspace
```

## Completed hardening work

```text
Runtime write helpers now prefer training_* SQL tables when the Training Campaign Lab schema is installed.
JSON runtime state remains as a local fallback when SQL is unavailable.
Proof files are no longer meant to be linked directly from upload paths.
A controlled proof file route was added.
Admin review and receipts routes now require Training Lab reviewer/admin permission.
POST actions continue to use the existing Local Quest CSRF protection loaded by app.php.
```

## Added files

```text
examples/local-quest-rewards/training-permissions.php
examples/local-quest-rewards/training-proof-file.php
```

## Updated files

```text
examples/local-quest-rewards/training-storage.php
examples/local-quest-rewards/training-receipt-service.php
examples/local-quest-rewards/training-reward-service.php
examples/local-quest-rewards/admin-training-review.php
examples/local-quest-rewards/admin-training-receipts.php
```

## SQL-backed write flow

When MySQL storage is configured and the Training Lab schema exists, these actions write to SQL:

```text
Join campaign -> training_participants
Submit proof -> training_files + training_task_submissions
Review proof -> training_reviews + training_task_submissions status update
Approved proof -> training_action_receipts
Sequence completion -> training_action_receipts
Reward eligibility -> training_reward_issues
Events -> training_events
```

## Fallback behavior

When SQL is not configured or the schema is not installed, the MVP continues using:

```text
examples/local-quest-rewards/data/training_lab_state.json
```

This keeps the local demo usable without database setup.

## Proof file access

Authenticated route:

```text
training-proof-file.php?file=STORED_FILENAME
```

Allowed viewers:

```text
The participant who owns the proof
Training Lab reviewer/admin users
```

Direct upload folder browsing should not be used as the product path.

## Reviewer/admin config

Training Lab reviewer/admin access checks read from `config.php`:

```php
'training_lab' => [
    'admin_emails' => ['owner@example.com'],
    'reviewer_emails' => ['reviewer@example.com'],
],
```

Fallback behavior:

```text
If training_lab.admin_emails and training_lab.reviewer_emails are empty, the helper attempts to use existing admin email config if present.
```

## CSRF protection

Training Lab pages load:

```php
require __DIR__ . '/app.php';
```

`app.php` boots the existing Local Quest session and auto-injects/verifies CSRF tokens for POST forms.

## Local QA commands

```bash
php scripts/validate_training_campaign_lab.php
php scripts/qa_training_campaign_lab_local.php
php -S 127.0.0.1:8090 -t examples/local-quest-rewards
```

## Next QA checks

```text
Install/import training_campaign_lab.sql
Import training_campaign_lab_seed.sql
Configure training_lab.admin_emails in config.php
Sign in as reviewer/admin
Join campaign as participant
Upload proof
Open admin review queue as reviewer/admin
Open proof using training-proof-file.php
Approve proof
Confirm training_action_receipts rows
Confirm training_reward_issues rows
Confirm admin receipts page loads
Confirm participant rewards/profile pages load
```
