# Training Lab Stage 2 Demo State Shell

Stage 2 browser-only demo-state shell for Training Lab by Microgifter.

Target host:

```text
https://labs.microgifter.com
```

Stage 2 rules:

```text
localStorage demo state only
no database writes
no real uploads
no payments
no wallet balance changes
no claim or redeem logic
no separate account system
no production DNS changes
```

## Local preview

From the repository root:

```bash
php -S 127.0.0.1:8091 -t labs
```

Open this local address in a browser:

```text
http://127.0.0.1:8091/
```

## Full PHP syntax check

From the repository root:

```bash
bash labs/run-full-syntax-check.sh
```

## Stage 2 demo test path

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

## Current files

```text
labs/
  index.php
  pricing.php
  how-it-works.php
  about.php
  team.php
  blog.php
  blog-article.php
  contact.php
  signup.php
  signin.php
  cart.php
  checkout.php
  success.php
  receipt.php
  routes-notes.md
  syntax-check-report.md
  full-syntax-check-report.md
  full-shell-review.md
  stage-2-demo-state-notes.md
  run-full-syntax-check.sh

  app/index.php
  app/campaigns.php
  app/campaign-detail.php
  app/sequence-tasks.php
  app/proof-upload.php
  app/rewards.php
  app/wallet.php

  admin/index.php
  admin/campaigns.php
  admin/review-queue.php

  includes/labs-layout.php
  includes/labs-components.php
  assets/css/labs.css
  assets/js/labs.js

  assets/img/marketing/training-lab-hero.svg
  assets/img/marketing/about-progress.svg
  assets/img/app/participant-dashboard.svg
  assets/img/admin/backend-overview.svg
  assets/img/icons/
```

## Current build state

The public, participant app, and backend shell pages exist with static/demo content.

Stage 2 adds browser-only demo state across proof, review, reward, and wallet surfaces using `localStorage`.

The demo state is wired through `labs/assets/js/labs.js` and the key app/backend pages.

Pages wired for Stage 2 demo state:

```text
labs/app/index.php
labs/app/sequence-tasks.php
labs/app/proof-upload.php
labs/admin/review-queue.php
labs/app/rewards.php
labs/app/wallet.php
```

Imported SVG image assets remain in place on landing, about, app dashboard, and backend overview.

Next pass: run local syntax check, browser-test the Stage 2 demo path, then open a PR back into `training-lab-stage-1-ui-shell` or `local-quest-workspace` depending on review preference.
