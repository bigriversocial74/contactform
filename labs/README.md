# Training Lab Stage 1 UI Shell

Stage 1 visual-first PHP shell for Training Lab by Microgifter.

Target host:

```text
https://labs.microgifter.com
```

Stage 1 rules:

```text
static UI shell only
no database writes
no uploads
no payments
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

  assets/img/marketing/
  assets/img/app/
  assets/img/admin/
  assets/img/icons/
```

## Current build state

The public, participant app, and backend shell pages exist with static demo content.

The shared layout, public navigation, footer navigation, workspace sidebar, responsive CSS, form styles, table styles, reusable component helpers, route notes, and image asset folder map are in place.

Polished pages now include the landing page, pricing page, about page, team page, blog pages, contact page, signup page, signin page, cart page, success page, receipt page, app dashboard, app campaigns, proof upload, progress page, wallet page, and backend overview.

The checkout flow reaches a visual success page and a static receipt preview.

The support page PHP syntax check pass is documented in `syntax-check-report.md`.

Next pass: image placement, remaining fine-detail polish, and full-shell review.
