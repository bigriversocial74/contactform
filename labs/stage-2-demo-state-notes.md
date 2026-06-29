# Training Lab Stage 2 Demo State Notes

Branch:

```text
training-lab-stage-2-demo-state
```

Base branch:

```text
training-lab-stage-1-ui-shell
```

## Scope

Stage 2 adds browser-only demo state so the Training Lab shell feels connected across key pages.

```text
localStorage only
no database writes
no real uploads
no payment integration
no wallet balance changes
no claim or redeem logic
no separate account system
no production hosting changes
```

## Demo state key

```text
trainingLabDemoStateV1
```

## Demo actions

```text
Submit Demo Proof
  proofStatus -> submitted
  reviewStatus -> in_review
  completedActions -> 5
  rewardStatus -> pending

Approve Demo
  proofStatus -> approved
  reviewStatus -> approved
  completedActions -> 5
  rewardStatus -> unlocked

Reset Demo State
  clears localStorage demo state
```

## Pages wired

```text
labs/app/index.php
labs/app/sequence-tasks.php
labs/app/proof-upload.php
labs/admin/review-queue.php
labs/app/rewards.php
labs/app/wallet.php
```

## Script updated

```text
labs/assets/js/labs.js
```

## Manual test path

```text
1. Open /app/index.php
2. Open /app/sequence-tasks.php
3. Open /app/proof-upload.php
4. Click Submit Demo Proof
5. Open /admin/review-queue.php
6. Click Approve Demo
7. Open /app/rewards.php
8. Open /app/wallet.php
9. Confirm status text has updated
10. Click Reset Demo State
```
