# Training Campaign Lab Security Decision Log

## Purpose

This document tracks security and permission decisions for the future Training Campaign Lab build.

This is a planning document only. It does not implement security controls.

## Decision status key

```text
Decided: Approved for implementation later
Proposed: Recommended but not approved
Open: Needs owner decision
Deferred: Not required for first MVP
Blocked: Cannot proceed until another decision is made
```

## Current security mode

```text
Documentation only
No implementation approved
No runtime security controls created yet
```

## Decisions

### SEC-001: Proof files are private by default

Status:

```text
Decided
```

Decision:

```text
Proof files submitted by participants must not be publicly browsable.
```

Future implementation rule:

```text
Store proof files outside public direct access where possible, or serve them only through an authenticated route.
```

Allowed viewers:

```text
Participant who submitted the proof
Authorized reviewer/admin
System automation where needed
```

Not allowed:

```text
Public proof directory browsing
Raw proof file links in public pages
Search engine indexing of proof files
```

### SEC-002: Reviewer/admin access is required for review actions

Status:

```text
Decided
```

Decision:

```text
Only authorized reviewers/admins can approve, reject, or request resubmission.
```

Future implementation rule:

```text
Every review POST action must check role/permission before changing status.
```

### SEC-003: Participant must be joined before proof submission

Status:

```text
Decided
```

Decision:

```text
A participant must join or be enrolled in a campaign before submitting proof.
```

Future implementation rule:

```text
Proof upload must verify participant enrollment before accepting the submission.
```

### SEC-004: CSRF is required for every POST action

Status:

```text
Decided
```

Decision:

```text
Every future Training Lab POST action must use CSRF protection.
```

POST actions include:

```text
join campaign
submit proof
approve submission
reject submission
request resubmission
create campaign
edit campaign
create reward rule
edit reward rule
settings changes
```

### SEC-005: No real reward issuing in first demo build

Status:

```text
Decided
```

Decision:

```text
The first future implementation should create reward issue records only. It should not call real Microgifter reward issuing APIs.
```

Reason:

```text
Proof/review/receipt logic must be validated before production reward issuance is connected.
```

### SEC-006: Action Receipt required before reward issue

Status:

```text
Decided
```

Decision:

```text
A reward issue record must not be created unless a verified Action Receipt exists.
```

Required chain:

```text
approved proof -> Action Receipt -> reward rule match -> reward issue record
```

### SEC-007: Admin/reviewer source of truth

Status:

```text
Open
```

Decision needed:

```text
Where should reviewer/admin roles be configured for the Training Lab?
```

Options:

```text
config.php email allowlist
existing Local Quest user roles
new training_participants role column
new dedicated training_roles table
```

Proposed MVP direction:

```text
Use a config.php email allowlist for reviewer/admin access in the first implementation, then migrate to role records later.
```

### SEC-008: Guest campaign browsing

Status:

```text
Proposed
```

Proposed decision:

```text
Guests may view public campaign summaries, but joining and proof submission require sign-in.
```

Reason:

```text
Proof and rewards require a durable participant identity.
```

### SEC-009: Proof file retention

Status:

```text
Open
```

Decision needed:

```text
How long should proof files be retained after review?
```

Options:

```text
Keep forever for audit
Keep until reward redemption
Keep for 90 days
Keep for campaign-defined retention period
Remove file but keep metadata receipt
```

Proposed MVP direction:

```text
Keep proof files during local demo/testing. Add retention policy before production.
```

### SEC-010: Rejected proof visibility

Status:

```text
Proposed
```

Proposed decision:

```text
Participant can see own rejected proof status and reviewer note. Other participants cannot see it.
```

Reviewer/admin visibility:

```text
Reviewer/admin can see rejected proof for audit and dispute handling.
```

### SEC-011: Resubmission behavior

Status:

```text
Proposed
```

Proposed decision:

```text
Participants cannot edit a submitted proof record. If resubmission is requested, they create a new attempt.
```

Reason:

```text
Preserves audit history and review integrity.
```

### SEC-012: Audit events are append-only

Status:

```text
Decided
```

Decision:

```text
Security-relevant Training Lab events should be append-only.
```

Events include:

```text
campaign joined
proof submitted
proof reviewed
Action Receipt created
reward issue record created
reward issue failed
settings changed
role changed
```

### SEC-013: Numeric IDs should not be exposed in URLs

Status:

```text
Decided
```

Decision:

```text
Future routes should use slugs or public IDs in URLs, not raw numeric database IDs.
```

### SEC-014: Production keys and secrets

Status:

```text
Decided
```

Decision:

```text
No production keys, API secrets, tokens, or credentials should be committed to the repo.
```

### SEC-015: Direct Local Quest file changes

Status:

```text
Decided
```

Decision:

```text
The first Training Lab implementation must not modify protected existing Local Quest files.
```

Protected files are listed in `implementation-boundaries.md`.

## Security gates before future implementation

Before code starts:

```text
[ ] SEC-007 admin/reviewer source of truth decided or explicitly deferred.
[ ] SEC-008 guest browsing decision confirmed.
[ ] SEC-009 proof retention decision deferred or decided.
[ ] CSRF mechanism identified in existing Local Quest code.
[ ] Authentication/session dependency identified.
[ ] Proof file route behavior documented.
```

## Security gates before production

Before production deployment:

```text
[ ] Proof files are private.
[ ] Review actions require reviewer/admin permission.
[ ] CSRF is active on every POST action.
[ ] Reward issuing cannot happen without Action Receipt.
[ ] Audit events are written for important actions.
[ ] No raw numeric IDs are required in public URLs.
[ ] No secrets are committed.
[ ] Retention policy is implemented.
[ ] Permission model is tested with participant and reviewer accounts.
```
