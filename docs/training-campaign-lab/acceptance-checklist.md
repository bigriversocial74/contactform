# Training Campaign Lab Acceptance Checklist

## Purpose

This checklist defines what must work before the first Training Campaign Lab vertical slice is considered complete.

## Documentation acceptance

- [ ] `docs/training-campaign-lab/README.md` exists.
- [ ] `docs/training-campaign-lab/expanded-platform-outline.md` exists.
- [ ] `docs/training-campaign-lab/build-plan.md` exists.
- [ ] `docs/training-campaign-lab/schema.md` exists.
- [ ] `docs/training-campaign-lab/status-model.md` exists.
- [ ] `docs/training-campaign-lab/mvp-scope.md` exists.
- [ ] `docs/training-campaign-lab/demo-script.md` exists.
- [ ] `docs/training-campaign-lab/acceptance-checklist.md` exists.

## Static shell acceptance

- [ ] `training-lab.php` loads without fatal errors.
- [ ] Training Campaign Lab overview is visible.
- [ ] 5-Day Movement Challenge card is visible.
- [ ] Coffee Shop Opening Routine card is visible.
- [ ] Creator Practice Streak card is visible.
- [ ] Reward ladder preview is visible.
- [ ] Action Receipt concept is visible.
- [ ] Participant flow link exists.
- [ ] Admin review link exists.

## Campaign data acceptance

- [ ] 5-Day Movement Challenge seed exists.
- [ ] Daily Movement Routine sequence exists.
- [ ] Warmup proof task exists.
- [ ] Squat video task exists.
- [ ] Plank video task exists.
- [ ] Cooldown proof task exists.
- [ ] Reward rule for sequence completion exists.
- [ ] Reward rule has Microgifter template/program mapping or test placeholder.

## Participant acceptance

- [ ] Participant can sign in or reuse existing Local Quest auth.
- [ ] Participant can join the 5-Day Movement Challenge.
- [ ] Participant status is stored.
- [ ] Participant can view sequence tasks.
- [ ] Participant can see task statuses.
- [ ] Participant can see reward locked/eligible/issued status.
- [ ] Participant can see progress count.

## Proof upload acceptance

- [ ] Participant can upload image proof.
- [ ] Participant can upload video proof.
- [ ] Upload rejects unsupported file type.
- [ ] Upload rejects file over configured max size.
- [ ] File metadata is stored.
- [ ] Proof submission is stored.
- [ ] Submission status becomes pending review.
- [ ] Proof upload event is logged.

## Admin review acceptance

- [ ] Admin review queue loads.
- [ ] Pending submissions are visible.
- [ ] Submission detail includes participant, campaign, sequence, task, proof file, and note.
- [ ] Reviewer can approve submission.
- [ ] Reviewer can reject submission.
- [ ] Reviewer can request resubmission.
- [ ] Reviewer notes are stored.
- [ ] Review event is logged.

## Resubmission acceptance

- [ ] Rejected submission does not count toward task completion.
- [ ] Needs-resubmission status is visible to participant.
- [ ] Participant can upload a replacement proof.
- [ ] New attempt is tracked.
- [ ] Approved resubmission counts toward task completion.

## Sequence completion acceptance

- [ ] Approved task updates task status.
- [ ] Required task count updates.
- [ ] Sequence does not complete while required tasks are pending.
- [ ] Sequence does not complete when a required task is rejected.
- [ ] Sequence completes when all required tasks are approved.
- [ ] Sequence status becomes verified complete.
- [ ] Sequence completion event is logged.

## Action Receipt acceptance

- [ ] Task approval can create task completion receipt or event.
- [ ] Sequence completion creates sequence Action Receipt.
- [ ] Action Receipt links participant, campaign, sequence, submissions, reviews, and timestamp.
- [ ] Action Receipt has public identifier.
- [ ] Action Receipt is visible in admin receipt/log view or event view.

## Reward acceptance

- [ ] Reward rule evaluates after sequence Action Receipt.
- [ ] Reward remains locked before sequence completion.
- [ ] Reward becomes eligible after sequence completion.
- [ ] Reward issue is blocked if Microgifter account link is required and missing.
- [ ] Reward issue is attempted when participant is linked.
- [ ] Reward issue response is stored.
- [ ] Reward status becomes issued or failed.
- [ ] Reward issue event is logged.
- [ ] Participant can see reward status.

## Consistency acceptance

MVP minimum:

- [ ] Verified sequence completion increments total completion count.
- [ ] Current streak updates after verified completion.
- [ ] Last completed date is stored.
- [ ] Next reward ladder requirement is visible.

Later:

- [ ] Missed day can mark streak at risk.
- [ ] Recovery/streak freeze can be applied.
- [ ] Weekly/monthly counts are tracked.

## Security and privacy acceptance

- [ ] Upload path is documented.
- [ ] Uploaded file names are sanitized or hashed.
- [ ] Allowed file types are enforced.
- [ ] File metadata stores mime type and file size.
- [ ] Proof visibility is limited to participant/admin/reviewer in MVP.
- [ ] Consent/retention decisions are documented even if not fully implemented.
- [ ] No biometric identity assumptions are made.

## Event log acceptance

Required events:

- [ ] `training.campaign.joined`
- [ ] `training.proof.uploaded`
- [ ] `training.review.approved`
- [ ] `training.review.rejected`
- [ ] `training.review.resubmission_requested`
- [ ] `training.sequence.verified_complete`
- [ ] `training.receipt.created`
- [ ] `training.reward.eligible`
- [ ] `training.reward.issued`
- [ ] `training.reward.failed`
- [ ] `training.streak.updated`

## Validation script acceptance

- [ ] `scripts/validate_training_campaign_lab.php` exists.
- [ ] Validator checks required docs.
- [ ] Validator checks required PHP files.
- [ ] Validator checks SQL file exists.
- [ ] Validator checks seed campaign definitions exist.
- [ ] Validator reports missing files clearly.
- [ ] Validator exits successfully when required pieces exist.

## Final vertical slice acceptance

The first build is accepted when this full sequence works:

```text
Participant joins campaign.
Participant sees four required tasks.
Participant uploads proof for all four tasks.
Admin approves all four submissions.
Sequence becomes verified complete.
Action Receipt is created.
Reward becomes eligible.
Reward is issued or a clear issue failure is stored.
Participant sees reward status.
Completion/streak count updates.
```
