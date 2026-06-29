# Training Campaign Lab Open Questions

## Purpose

This document tracks unresolved decisions for the Training Campaign Lab.

The goal is to avoid burying product, security, data, and UX decisions inside code.

## Decision status values

```text
Open
Proposed
Decided
Deferred
Blocked
```

## Branch and build questions

### Q1. Should Training Lab remain permanently separate from Loyalty Quest?

Status: Proposed

Current direction:

```text
Yes. Treat local-quest-workspace as a duplicate/fork workspace of Loyalty Quest where Training Campaign Lab evolves separately.
```

Reason:

```text
Protects existing Loyalty Quest behavior while allowing a new proof-of-action campaign product to develop.
```

### Q2. Should existing Local Quest files be modified during the Training Lab MVP?

Status: Proposed

Current direction:

```text
No, not unless explicitly approved.
```

Reason:

```text
The current goal is to build beside the Loyalty Quest flow, not replace it.
```

## Product scope questions

### Q3. Should viewing campaigns require sign-in?

Status: Open

Options:

```text
Option A: Public campaign browsing, sign-in required only to join/upload proof.
Option B: Sign-in required before seeing any campaign.
Option C: Public campaigns are visible, private/team campaigns require sign-in.
```

Proposed MVP direction:

```text
Use Option C later. Phase 1 can allow guest/demo viewing.
```

### Q4. Does reward eligibility happen after one sequence or full campaign completion?

Status: Proposed

Current MVP direction:

```text
Reward eligibility should be triggered by sequence completion for the first MVP.
```

Reason:

```text
This keeps the first vertical slice small and testable.
```

Later expansion:

```text
Campaign completion, streaks, milestones, and multi-sequence rewards can be added later.
```

### Q5. Should Training Lab have its own wallet page or reuse Local Quest wallet?

Status: Open

Options:

```text
Option A: Training Lab has its own profile/wallet page and links to Local Quest wallet.
Option B: Training Lab directly uses the existing Local Quest wallet.
Option C: Training Lab replaces Local Quest wallet in this branch only.
```

Proposed direction:

```text
Option A.
```

Reason:

```text
Keeps the Training Lab UI clear while preserving the existing wallet flow.
```

## Proof and review questions

### Q6. Should proof files be private by default?

Status: Proposed

Current direction:

```text
Yes. Proof files should be private by default.
```

MVP note:

```text
If files are stored in a public folder during early MVP, production hardening must move access behind permission checks.
```

### Q7. Can participants edit proof after submission?

Status: Open

Options:

```text
Option A: No edit after submit; submit a new attempt only if rejected/resubmission requested.
Option B: Allow edit while pending review.
Option C: Allow participant to withdraw pending proof and resubmit.
```

Proposed MVP direction:

```text
Option A.
```

Reason:

```text
Keeps audit and review history simpler.
```

### Q8. Should review be admin-only or a separate reviewer role?

Status: Proposed

Current MVP direction:

```text
Use existing admin/reviewer simplification for MVP, but document Reviewer as a separate role for later.
```

Reason:

```text
MVP should prove proof/review/reward flow before building full permissions UI.
```

## Reward questions

### Q9. Should a reward issue be automatically attempted when eligibility is reached?

Status: Open

Options:

```text
Option A: Automatically attempt reward issue when eligible and linked account exists.
Option B: Mark eligible, then admin manually releases reward.
Option C: Mark eligible, then participant clicks claim/release.
```

Proposed MVP direction:

```text
Option A if Microgifter config is valid; otherwise store needs_config or needs_linked_account state.
```

### Q10. How should missing Microgifter account link be handled?

Status: Proposed

Current direction:

```text
Reward issue status becomes needs_linked_account.
Participant sees link-account CTA.
Reward eligibility is preserved.
```

### Q11. Should rewards be reissued after failed issue?

Status: Open

Proposed direction:

```text
Allow admin retry later, but MVP can record failed status and display it clearly.
```

## Schema and data questions

### Q12. Should Training Lab use separate SQL tables or extend existing Local Quest state?

Status: Proposed

Current direction:

```text
Use separate training_* SQL tables.
```

Reason:

```text
Keeps Training Lab data independent and avoids breaking existing quest data.
```

### Q13. Should seed data live in PHP arrays or SQL seed files?

Status: Proposed

Current direction:

```text
Phase 1 uses PHP arrays.
Phase 3 adds SQL seed file.
```

### Q14. Should public IDs be UUIDs or generated slugs?

Status: Open

Options:

```text
UUID public_id for database records.
Readable slug for campaign URLs.
Both where useful.
```

Proposed direction:

```text
Use UUID-style public_id for records and stable slugs for campaign routing.
```

## UI/UX questions

### Q15. Should mobile use bottom navigation for admin pages?

Status: Open

Proposed direction:

```text
Participant pages use bottom nav.
Admin pages use hamburger/menu and page-level actions.
```

### Q16. Should proof upload be its own page or a modal from Sequence / Tasks?

Status: Proposed

Current direction:

```text
Use its own page for MVP.
```

Reason:

```text
Uploads, validation, notes, previous submissions, and mobile sticky CTA are easier to manage on a dedicated page.
```

### Q17. Should Action Receipts be participant-visible?

Status: Proposed

Current direction:

```text
Participants should see their own receipts in rewards/profile history.
Admins should see all permitted receipts in admin receipts page.
```

## Admin workflow questions

### Q18. Should templates and builder be built before proof/review/reward flow?

Status: Proposed

Current direction:

```text
No. Build proof/review/reward vertical slice first.
```

Reason:

```text
Templates and builder are valuable but should not delay proving the core data loop.
```

### Q19. Should settings be functional in MVP?

Status: Proposed

Current direction:

```text
No. Settings can be static/stubbed until core flow works.
```

## Final open decision list before Phase 2

Before SQL implementation, decide:

```text
final table list
public_id format
campaign slug format
proof upload path
max upload size
review role simplification
reward issue auto/manual behavior
wallet integration approach
```

## Decision log

| Date | Question | Decision | Notes |
|---|---|---|---|
| TBD | Branch strategy | Proposed separate branch | local-quest-workspace remains separate |
| TBD | MVP reward trigger | Proposed sequence completion | Keeps first vertical slice focused |
| TBD | Proof file access | Proposed private by default | MVP may use temporary local folder with hardening later |
