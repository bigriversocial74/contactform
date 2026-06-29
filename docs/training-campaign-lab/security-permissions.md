# Training Campaign Lab Security and Permissions

## Purpose

This document defines access control, role permissions, proof file visibility, review authority, reward issue authority, and audit expectations for the Training Campaign Lab.

The product collects proof files, participant progress, review decisions, and reward activity. These records must be protected before the build moves beyond static UI.

## Security principles

```text
Least privilege by default
Participants can only access their own progress and proof
Reviewers can only review assigned/permitted campaign submissions
Admins can manage campaign operations
Owners can manage organization-level settings and audit logs
Proof files are private by default
Rewards cannot issue without approved proof and Action Receipts
Audit events are written for meaningful state changes
```

## Role model

## Guest

A non-authenticated visitor.

Allowed:

```text
View public landing/demo pages in Phase 1
View static campaign marketing content if the campaign is public later
```

Not allowed:

```text
Join campaign
Upload proof
View participant progress
View proof files
Review submissions
Create receipts
Issue rewards
Access admin pages
Access audit logs
```

## Participant

A signed-in user who joins campaigns and completes tasks.

Allowed:

```text
View campaigns available to them
Join eligible campaigns
View own campaign detail
View own sequence/tasks
Upload proof for own assigned/available tasks
View own submission status
Resubmit proof when rejected or requested
View own Action Receipts
View own reward status
View own wallet/profile
```

Not allowed:

```text
View other participants' proof files
Approve/reject proof
Create Action Receipts manually
Manually issue rewards
View admin reports
View global audit logs
Modify reward rules
Modify campaign settings
```

## Reviewer

A user trusted to review proof submissions.

Allowed:

```text
View pending submissions for permitted campaigns
View proof files for assigned/permitted submissions
Approve proof
Reject proof
Request resubmission
Add reviewer notes
View review history for permitted campaigns
View Action Receipts tied to reviewed submissions where allowed
```

Not allowed by default:

```text
Create campaigns
Change reward rules
Issue rewards manually
Manage organization settings
View unrelated campaigns
View unrelated proof files
View all audit logs unless also admin
```

## Manager / Campaign Admin

A user who manages campaigns and participants.

Allowed:

```text
View campaign dashboard
View participants for managed campaigns
View team progress
Invite/add participants when enabled
View review queue for managed campaigns
View Action Receipts for managed campaigns
Configure campaigns later
Configure reward rules later if granted
```

Not allowed by default:

```text
Change organization-wide settings
View all organizations
Access unrelated campaign proof
Bypass reward eligibility rules
```

## Owner / Organization Admin

A top-level organization administrator.

Allowed:

```text
Manage organization settings
Manage team permissions
Manage integrations
View all campaigns in organization
View all participant records in organization
View all proof submissions in organization
View all Action Receipts in organization
View audit logs
Configure reward rules
Approve administrative changes
```

Must still obey:

```text
Reward issue path requires verified eligibility
Proof review decisions should be logged
Sensitive file access should be auditable
```

## System / Automation

Server-side process responsible for receipt creation, rule evaluation, reward issue attempts, and event logging.

Allowed:

```text
Create Action Receipts after approved proof
Evaluate reward rules
Create reward issue records
Call Microgifter reward/distribution flow where configured
Update reward issue status
Write system events
```

Not allowed:

```text
Create reward issue without eligible receipt/rule
Silently modify participant proof status without event log
Delete proof/review/receipt records without retention process
```

## Page access matrix

| Page | Guest | Participant | Reviewer | Manager/Admin | Owner |
|---|---:|---:|---:|---:|---:|
| `training-lab.php` | Phase 1 yes | Yes | Yes | Yes | Yes |
| `training-campaigns.php` | Phase 1 yes | Yes | Yes | Yes | Yes |
| `training-campaign-detail.php` | Public campaigns only | Own/eligible | View if assigned | Managed campaigns | All org campaigns |
| `training-sequence.php` | No | Own campaigns only | View if assigned | Managed campaigns | All org campaigns |
| `training-proof-upload.php` | No | Own tasks only | No upload unless participant | No upload unless participant | No upload unless participant |
| `training-rewards.php` | No | Own rewards | Limited if assigned | Managed campaigns | All org rewards |
| `training-profile-wallet.php` | No | Own profile/wallet | Own profile/wallet | Own profile/wallet | Own profile/wallet |
| `admin-training-review.php` | No | No | Assigned/permitted | Managed campaigns | All org |
| `admin-training-participants.php` | No | No | Limited if assigned | Managed campaigns | All org |
| `admin-training-receipts.php` | No | Own receipts only via user route | Assigned/permitted | Managed campaigns | All org |
| `admin-training-settings.php` | No | No | No | Limited if granted | Yes |
| `admin-training-templates.php` | No | No | No | Yes | Yes |
| `admin-training-builder.php` | No | No | No | Yes | Yes |
| `admin-training-reward-rules.php` | No | No | No | If granted | Yes |
| `admin-training-audit-logs.php` | No | No | No | Limited if granted | Yes |

## Proof file access

Proof files are private by default.

Allowed viewers:

```text
Participant who uploaded the proof
Reviewer assigned/permitted to review the submission
Manager/admin with access to the campaign
Owner/org admin
System process for validation, review, receipt, or reward processing
```

Not allowed:

```text
Other participants
Public/guest visitors
Unassigned reviewers
Unrelated managers
External users without authorization
```

## Proof upload protection

MVP upload requirements:

```text
Accept only allowed extensions
Validate file metadata where practical
Enforce max file size
Generate safe stored filename
Do not trust original filename
Store file metadata separately from submission
Prevent unsafe path handling
Store outside public web root if possible later
If public folder is used during MVP, protect access with route-level authorization before production
```

Allowed MVP extensions:

```text
jpg
jpeg
png
webp
mp4
mov
webm
```

## Review security

Reviewer actions must:

```text
Require authenticated reviewer/admin user
Check campaign access
Check submission is reviewable
Record reviewer ID
Record reviewer note where present
Record reviewed_at timestamp
Write training_events record
Prevent duplicate final review action unless explicitly reopened
```

Review actions:

```text
approve
reject
request_resubmission
```

## Reward security

Rewards must not issue directly from upload.

Required reward preconditions:

```text
Participant is valid
Campaign is valid
Task/submission has approved review
Action Receipt exists
Reward rule is active
Reward rule evaluates eligible
Reward issue does not already exist for the same receipt/rule/user
Participant has linked Microgifter account or issue status becomes needs_linked_account
```

Blocked reward conditions:

```text
Unapproved proof
Rejected proof
Needs resubmission proof
Expired campaign when rule disallows it
Duplicate issue attempt
Budget/cap reached
Missing Microgifter template/program
Missing linked account
Invalid config
```

## Action Receipt security

Action Receipts should be created by server-side logic only.

Do not allow users to manually create receipts from a form.

Receipt creation must verify:

```text
Approved review exists
Submission belongs to participant/campaign/task
No duplicate receipt exists for the same verified action
Required tasks are complete before sequence receipt creation
```

## Audit requirements

Log events for:

```text
campaign joined
proof uploaded
submission status changed
review approved
review rejected
review resubmission requested
task receipt created
sequence receipt created
reward eligible
reward issue pending
reward issued
reward failed
reward viewed/status checked
settings changed later
permission changed later
```

Event records should include where practical:

```text
actor user ID
actor role
event type
target record type
target public ID
campaign public ID
participant public ID
status before/status after
timestamp
metadata payload
```

## CSRF/session expectations

The Training Lab should reuse existing Local Quest security/session patterns where safe.

Expected:

```text
POST actions require CSRF protection
Session is booted before auth checks
Auth is checked server-side
Permission is checked server-side
UI hiding is not permission enforcement
```

## MVP permission shortcuts

For early MVP, it is acceptable to begin with simplified role checks if documented.

Allowed temporary simplification:

```text
Authenticated Local Quest user can act as demo participant
Existing admin route/session can act as reviewer/admin
```

Not allowed even in MVP:

```text
Reward from unapproved proof
Public proof file browsing
Unsigned destructive actions
Duplicate reward issues
Changing main/original Loyalty Quest behavior without approval
```

## Pre-production hardening checklist

Before production use:

```text
Move proof files behind authenticated access route
Add stricter file validation
Add admin role separation
Add reviewer assignment model
Add signed URLs or controlled file streaming
Add audit log export rules
Add retention/deletion process
Add rate limits on upload/review actions
Add stronger duplicate reward protection
```
