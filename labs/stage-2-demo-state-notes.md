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
labs/app/campaigns.php
labs/app/campaign-detail.php
labs/app/sequence-tasks.php
labs/app/proof-upload.php
labs/admin/index.php
labs/admin/review-queue.php
labs/app/rewards.php
labs/app/wallet.php
```

## Script updated

```text
labs/assets/js/labs.js
```

## Browser test checklist

```text
labs/stage-2-browser-test-checklist.md
```

## Manual test path

```text
1. Open /app/index.php
2. Open /app/campaigns.php
3. Open /app/campaign-detail.php
4. Open /app/sequence-tasks.php
5. Open /app/proof-upload.php
6. Click Submit Demo Proof
7. Open /admin/index.php
8. Open /admin/review-queue.php
9. Click Approve Demo
10. Open /app/rewards.php
11. Open /app/wallet.php
12. Confirm status text has updated
13. Click Reset Demo State
```
