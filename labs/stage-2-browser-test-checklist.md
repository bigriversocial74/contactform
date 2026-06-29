# Training Lab Stage 2 Browser Test Checklist

Branch:

```text
training-lab-stage-2-demo-state
```

## Local setup

```bash
php -S 127.0.0.1:8091 -t labs
```

Open:

```text
http://127.0.0.1:8091/
```

## Reset before test

Open any wired page and click `Reset Demo State`, or clear browser localStorage for:

```text
trainingLabDemoStateV1
```

## Participant path

```text
/app/index.php
  Confirm proof status shows Not submitted.
  Confirm reward status shows Pending.
  Confirm progress shows 80% complete.

/app/campaigns.php
  Confirm 5-Day Movement Challenge uses demo state.

/app/campaign-detail.php
  Confirm Day 5 Proof uses demo state.

/app/sequence-tasks.php
  Confirm Day 5 Movement Proof uses demo state.

/app/proof-upload.php
  Click Submit Demo Proof.
  Confirm proof status changes to Submitted.
  Confirm review status changes to In review.
```

## Backend review path

```text
/admin/index.php
  Confirm Primary review shows In review after proof submit.

/admin/review-queue.php
  Confirm Jamie R. row shows In review.
  Click Approve Demo.
  Confirm row changes to Approved.
```

## Reward and wallet path

```text
/app/rewards.php
  Confirm reward status changes to Unlocked after approval.
  Confirm progress shows 100% complete.

/app/wallet.php
  Confirm Movement Milestone changes to Unlocked after approval.
  Confirm Last update is populated.
```

## Reset path

```text
/app/wallet.php
  Click Reset Demo.
  Confirm proof status returns to Not submitted on wired pages.
  Confirm reward status returns to Pending.
```

## Safety checks

```text
No network request should be required for demo state.
No file upload should occur.
No payment action should occur.
No claim code should be generated.
No wallet balance should change.
No account/session should be created.
```
