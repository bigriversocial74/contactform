# Training Campaign Lab UI Page Map

## Purpose

This document locks the app page structure before implementation so desktop and mobile builds stay aligned with the approved mockups.

## Primary app pages

```text
1. App Landing / Dashboard
2. Campaigns
3. Campaign Detail
4. Sequence / Tasks
5. Proof Upload
6. Rewards & Progress
7. Review Queue
8. Participants & Teams
9. Action Receipts & History
10. Settings
11. Templates
12. Campaign Builder
13. Reward Rules Builder
14. User Profile / Wallet
15. Audit Logs
```

## Page groups

### Participant experience

```text
Dashboard
Campaigns
Campaign Detail
Sequence / Tasks
Proof Upload
Rewards & Progress
User Profile / Wallet
```

Primary participant flow:

```text
Campaigns -> Campaign Detail -> Sequence / Tasks -> Proof Upload -> Pending Review -> Rewards & Progress -> User Profile / Wallet
```

### Reviewer experience

```text
Review Queue
Action Receipts & History
Audit Logs
```

Primary reviewer flow:

```text
Review Queue -> Submission Detail -> Approve / Reject / Request Resubmission -> Action Receipt -> Reward Status
```

### Admin / merchant experience

```text
Dashboard
Campaigns
Templates
Campaign Builder
Reward Rules Builder
Participants & Teams
Action Receipts & History
Audit Logs
Settings
```

Primary admin flow:

```text
Templates -> Campaign Builder -> Reward Rules Builder -> Participants & Teams -> Review Queue -> Action Receipts -> Audit Logs
```

## Route targets

Recommended first PHP page routes:

```text
examples/local-quest-rewards/training-lab.php
examples/local-quest-rewards/training-campaigns.php
examples/local-quest-rewards/training-campaign-detail.php
examples/local-quest-rewards/training-sequence.php
examples/local-quest-rewards/training-proof-upload.php
examples/local-quest-rewards/training-rewards.php
examples/local-quest-rewards/admin-training-review.php
examples/local-quest-rewards/admin-training-participants.php
examples/local-quest-rewards/admin-training-receipts.php
examples/local-quest-rewards/admin-training-settings.php
examples/local-quest-rewards/admin-training-templates.php
examples/local-quest-rewards/admin-training-builder.php
examples/local-quest-rewards/admin-training-reward-rules.php
examples/local-quest-rewards/training-profile-wallet.php
examples/local-quest-rewards/admin-training-audit-logs.php
```

## Navigation model

### Desktop navigation

Desktop uses a persistent left sidebar.

Primary sidebar items:

```text
Dashboard / Overview
Campaigns
Sequences / Tasks
Proof Upload or Proof & Submissions
Rewards
Review Queue
Participants / Teams
Action Receipts
Templates
Settings
Audit Logs
Profile / Wallet
```

### Mobile navigation

Mobile uses:

```text
Top app bar
Hamburger menu
Bottom tab bar for core participant actions
Sticky bottom CTA for task/proof/reward actions
```

Suggested mobile bottom nav:

```text
Overview
Campaigns
Sequences
Rewards
Profile
```

Reviewer mobile bottom nav:

```text
Overview
Review Queue
Rewards
Messages
More
```

## MVP route priority

Build the first pages in this order:

```text
1. training-lab.php
2. training-campaigns.php
3. training-campaign-detail.php
4. training-sequence.php
5. training-proof-upload.php
6. admin-training-review.php
7. training-rewards.php
8. admin-training-receipts.php
9. admin-training-participants.php
```

Admin creation pages can follow after the participant/reviewer vertical slice works:

```text
10. admin-training-templates.php
11. admin-training-builder.php
12. admin-training-reward-rules.php
13. admin-training-settings.php
14. training-profile-wallet.php
15. admin-training-audit-logs.php
```
