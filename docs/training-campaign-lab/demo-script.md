# Training Campaign Lab Demo Script

## Purpose

This demo script defines the walkthrough the first build should support.

The demo should prove that Microgifter can reward verified action sequences.

## Demo title

```text
5-Day Movement Challenge
```

## Demo promise

```text
Complete a training sequence, upload proof, get reviewed, unlock a Microgifter reward, and track progress toward consistency.
```

## Demo roles

### Participant

The person completing the routine and uploading proof.

### Admin / Reviewer

The person reviewing proof and approving or rejecting task submissions.

### Microgifter reward system

The reward distribution layer that issues the campaign reward after the sequence is verified.

## Demo setup

Before starting the demo, confirm:

- Training Lab landing page exists.
- 5-Day Movement Challenge seed campaign exists.
- Daily Movement Routine sequence exists.
- Four tasks exist.
- Participant can sign in.
- Admin/reviewer can access review queue.
- Reward rule exists for verified sequence completion.
- Microgifter API configuration is available or a sandbox/test issue path exists.

## Demo campaign

Campaign:

```text
5-Day Movement Challenge
```

Sequence:

```text
Daily Movement Routine
```

Tasks:

1. Upload warmup photo/video.
2. Upload squat video.
3. Upload plank video.
4. Upload cooldown proof.

Reward:

```text
Complete all required tasks and receive a campaign Microgift reward.
```

## Walkthrough

## Step 1: Open Training Lab

URL:

```text
/examples/local-quest-rewards/training-lab.php
```

Expected result:

- Training Campaign Lab overview is visible.
- Campaign cards are visible.
- 5-Day Movement Challenge card is visible.
- Links to participant flow and admin review are visible.

## Step 2: Open campaign list

URL:

```text
/examples/local-quest-rewards/training-campaigns.php
```

Expected result:

- 5-Day Movement Challenge is listed.
- Coffee Shop Opening Routine is listed.
- Creator Practice Streak is listed.
- Each campaign shows sequence count, task count, and reward ladder preview.

## Step 3: Join campaign as participant

Participant action:

```text
Click Join Campaign.
```

Expected result:

- Participant is signed in or redirected to sign in.
- Participant joins the 5-Day Movement Challenge.
- Participant status becomes joined/active.
- Participant sees Daily Movement Routine.

## Step 4: View sequence tasks

URL:

```text
/examples/local-quest-rewards/training-sequence.php?campaign=5-day-movement-challenge
```

Expected result:

- Four required tasks are shown.
- Each task starts as not started or pending proof.
- Reward preview is shown as locked.
- Progress reads 0 of 4 tasks approved.

## Step 5: Upload proof for Task 1

Participant action:

```text
Open Upload Proof for Warmup.
Upload image or video.
Add optional note.
Submit.
```

Expected result:

- File metadata is stored.
- Submission status becomes pending_review.
- Task status becomes submitted or under_review.
- Event log records proof upload.

## Step 6: Upload proof for remaining tasks

Repeat upload flow for:

```text
Squat video
Plank video
Cooldown proof
```

Expected result:

- All four tasks have pending proof submissions.
- Participant progress shows 0 approved / 4 submitted.

## Step 7: Open admin review queue

URL:

```text
/examples/local-quest-rewards/admin-training-review.php
```

Expected result:

- Pending proof submissions are listed.
- Each row shows participant, campaign, task, upload time, and proof file.
- Reviewer can open proof details.

## Step 8: Approve proof submissions

Admin action:

```text
Approve each submitted proof.
Add reviewer notes.
```

Expected result:

- Submission status becomes approved.
- Task status becomes approved.
- Review record is created.
- Event log records review approval.

## Step 9: Sequence completion

System action:

```text
After the fourth required task is approved, evaluate sequence completion.
```

Expected result:

- Sequence status becomes verified_complete.
- Sequence Action Receipt is created.
- Participant campaign status becomes completed or reward_eligible.
- Event log records sequence completion.

## Step 10: Reward rule evaluation

System action:

```text
Evaluate reward rules after Action Receipt creation.
```

Expected result:

- Reward rule for sequence completion becomes eligible.
- Reward issue status becomes pending_issue.
- If Microgifter account is linked, reward issue is attempted.
- If not linked, user is prompted to connect Microgifter before reward issue.

## Step 11: Reward issue

System action:

```text
Issue Microgifter reward through existing distribution flow where practical.
```

Expected result:

- Reward issue is stored.
- Microgifter response is stored.
- Reward status becomes issued or failed.
- Participant sees reward status.

## Step 12: Wallet view

URL:

```text
/examples/local-quest-rewards/wallet.php
```

or module-specific:

```text
/examples/local-quest-rewards/training-rewards.php
```

Expected result:

- Issued reward is visible.
- Claim status is visible.
- Reward is linked to the Training Campaign Action Receipt.

## Step 13: Consistency preview

URL:

```text
/examples/local-quest-rewards/training-consistency.php
```

Expected result:

- Total verified sequences count is visible.
- Current streak count is visible.
- Reward ladder preview is visible.
- Next unlock requirement is visible.

## Admin talking points

Use these lines during the demo:

```text
This is no longer just a quest. This is a verified training sequence.
```

```text
Every approved task creates proof. Every completed sequence creates an Action Receipt.
```

```text
Rewards are not issued for clicks. Rewards are issued for verified progress.
```

```text
The same structure can support fitness, staff training, creator practice, safety routines, and sponsor-funded challenges.
```

## Demo success moment

The key success moment is:

```text
Sequence complete. Action Receipt created. Campaign reward issued.
```

## Demo failure cases to show later

After MVP works, show:

- proof rejected
- resubmission requested
- reward blocked because Microgifter account is not linked
- reward blocked by budget cap
- sequence incomplete because one required task is pending
- streak at risk because user missed a day

## Demo data summary

The final demo should show:

```text
Participant: signed in
Campaign: 5-Day Movement Challenge
Sequence: Daily Movement Routine
Tasks: 4 required
Proof submissions: 4
Approvals: 4
Action Receipts: 1 sequence completion
Reward status: issued
Streak: 1 verified sequence
```
