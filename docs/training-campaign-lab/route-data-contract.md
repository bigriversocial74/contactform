# Training Campaign Lab Route Data Contract

## Purpose

This document defines the expected data contract for each future Training Campaign Lab route.

This is planning only. It does not approve route implementation.

## Current status

```text
Documentation only
No route files should be created yet
```

## Route contract rules

Future routes must follow these rules:

```text
Use campaign slug or public_id in URLs, not raw numeric IDs.
Use prepared statements for all SQL reads/writes.
Use CSRF protection for every POST action.
Use role/permission checks before admin/reviewer actions.
Never issue a reward directly from proof upload.
Never make proof files publicly browsable.
Never change existing Local Quest routes during the first Training Lab build.
```

## Public / participant routes

### training-lab.php

Purpose:

```text
Training Campaign Lab dashboard and entry point.
```

Reads:

```text
Campaign count
Active campaign summaries
Participant progress summary for current user if available
Review/reward preview counts where allowed
```

Writes:

```text
None in initial version
```

Primary actions:

```text
Open campaigns
Open featured campaign
Open current sequence if participant has joined
Open rewards/profile
Return to Local Quest
```

### training-campaigns.php

Purpose:

```text
Campaign library.
```

Reads:

```text
Campaign list
Campaign status
Campaign tags/type
Sequence count
Task count
Participant count
Reward preview
```

Writes:

```text
None
```

Primary actions:

```text
Filter campaigns
Search campaigns
Open campaign detail
```

### training-campaign-detail.php

Purpose:

```text
Campaign overview, sequence preview, reward ladder, and join CTA.
```

URL parameters:

```text
campaign={campaign_slug}
```

Reads:

```text
Campaign details
Sequence summaries
Task summaries
Reward rules
Current participant enrollment state
Current participant progress if joined
```

Writes:

```text
Join campaign action only after implementation approval
```

POST actions:

```text
join_campaign
```

POST requirements:

```text
Authenticated user
CSRF token
Duplicate join prevention
Event record
```

### training-sequence.php

Purpose:

```text
Participant task sequence and progress page.
```

URL parameters:

```text
campaign={campaign_slug}
sequence={sequence_slug optional}
```

Reads:

```text
Campaign
Sequence
Tasks
Participant enrollment
Latest task submissions
Review status per task
Progress percentage
```

Writes:

```text
None in initial sequence view
```

Primary actions:

```text
Open proof upload for current task
Open campaign detail
Open rewards
```

### training-proof-upload.php

Purpose:

```text
Participant proof submission page.
```

URL parameters:

```text
campaign={campaign_slug}
task={task_slug}
```

Reads:

```text
Campaign
Task
Proof requirements
Participant enrollment
Latest submission for task
```

Writes:

```text
training_files
training_task_submissions
training_events
```

POST actions:

```text
submit_proof
```

POST requirements:

```text
Authenticated participant
Joined campaign
CSRF token
Allowed file type
Allowed file size
Safe stored filename
Private proof file storage
Event record
```

Not allowed:

```text
No review decision
No Action Receipt creation
No reward issue creation
```

### training-rewards.php

Purpose:

```text
Participant reward progress and reward issue status.
```

URL parameters:

```text
campaign={campaign_slug optional}
```

Reads:

```text
Campaign reward rules
Participant progress
Action Receipts
Reward issue records
Linked account status
```

Writes:

```text
None in MVP display route
```

Primary actions:

```text
Open sequence
Open profile/wallet
Open Local Quest wallet
```

### training-profile-wallet.php

Purpose:

```text
Training-specific participant profile and wallet bridge.
```

Reads:

```text
Participant profile
Joined campaigns
Training receipts
Training reward issues
Linked account state
Recent events visible to participant
```

Writes:

```text
None in initial MVP
```

Primary actions:

```text
Open Training rewards
Open Local Quest wallet
Open campaign sequence
```

## Admin / reviewer routes

### admin-training-review.php

Purpose:

```text
Proof review queue.
```

Reads:

```text
Pending submissions
Proof metadata
Participant details
Campaign details
Task requirements
Latest review history
```

Writes:

```text
training_reviews
training_task_submissions status
training_events
```

POST actions:

```text
approve_submission
reject_submission
request_resubmission
```

POST requirements:

```text
Reviewer/admin permission
CSRF token
Reviewer note where required
Event record
Status transition validation
```

Approval side effects:

```text
Approved task submission may trigger Action Receipt evaluation in later stage.
```

Not allowed:

```text
No direct real reward issuing from review page
No public proof sharing
```

### admin-training-receipts.php

Purpose:

```text
Verified action ledger for reviewers/admins.
```

Reads:

```text
Action Receipts
Receipt source submission
Review decision
Reward issue link if present
Event timeline
```

Writes:

```text
None in MVP admin receipt viewer
```

Permissions:

```text
Reviewer/admin only
```

### admin-training-participants.php

Purpose:

```text
Participant monitoring and campaign participation overview.
```

Reads:

```text
Campaign participants
Progress by participant
Submission counts
Receipt counts
Reward issue counts
```

Writes:

```text
None in initial viewer
```

Future writes:

```text
Invite participant
Remove participant
Change reviewer role
```

### admin-training-builder.php

Purpose:

```text
Campaign creation and editing interface.
```

Reads:

```text
Campaign draft
Sequences
Tasks
Reward rules
Templates
```

Writes:

```text
training_campaigns
training_sequences
training_tasks
training_reward_rules
training_events
```

Implementation rule:

```text
Builder should be implemented after the first proof/review/reward vertical slice works.
```

### admin-training-reward-rules.php

Purpose:

```text
Reward rule setup and editing.
```

Reads:

```text
Campaigns
Reward rules
Budget caps
Linked Microgifter program/template options
```

Writes:

```text
training_reward_rules
training_events
```

Safety rule:

```text
Changing reward rules must not retroactively issue rewards unless explicitly configured.
```

### admin-training-settings.php

Purpose:

```text
Training Lab configuration screen.
```

Reads:

```text
Reviewer/admin config
Proof settings
Default file limits
Reward issue mode
Retention settings
```

Writes:

```text
Configuration storage after config strategy is decided
```

### admin-training-audit-logs.php

Purpose:

```text
Audit trail viewer.
```

Reads:

```text
training_events
```

Writes:

```text
None
```

Permissions:

```text
Admin only or reviewer/admin depending decision log
```

## Private file route

### training-proof-file.php

Purpose:

```text
Authenticated proof file access route.
```

URL parameters:

```text
file={stored_filename or proof_public_id}
```

Reads:

```text
training_files
training_participants
current user
```

Writes:

```text
Optional file access event
```

Access rules:

```text
Participant can view own proof
Reviewer/admin can view submissions they are authorized to review
Public users cannot view proof files
```

## Route-to-status transitions

```text
training-proof-upload.php: no submission -> pending_review
admin-training-review.php approve: pending_review -> approved
admin-training-review.php reject: pending_review -> rejected
admin-training-review.php request resubmission: pending_review -> needs_resubmission
receipt service: approved -> task_completion receipt
receipt service: all required approved -> sequence_completion receipt
reward service: sequence_completion receipt -> reward issue status
```

## Non-route services

Future service/helper files:

```text
training-storage.php
training-receipt-service.php
training-reward-service.php
training-permissions.php
```

These should not output HTML. They should provide data access, permission checks, and business logic only.

## Implementation note

This contract should be checked before creating any future route file. If a route needs data not described here, update this document first.
