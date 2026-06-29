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
no reward issuing
no separate account system
no production DNS changes
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
  assets/css/labs.css
  assets/js/labs.js
```

## Current build state

The public, participant app, and admin shell pages exist with static demo content.

Next pass: refine visual details, route consistency, shared components, mobile spacing, and image placement.
