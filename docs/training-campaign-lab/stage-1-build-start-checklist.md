# Stage 1 Build Start Checklist

## Purpose

This checklist defines what is approved for the first Training Lab build phase and what must remain out of scope.

Stage 1 should create the visual UI shell and navigation foundation for Training Lab by Microgifter. It should not implement backend logic, database writes, payments, real uploads, or real reward issuing.

## Stage 1 status

```text
Approved direction: UI shell foundation
Implementation type: static / visual-first PHP or HTML shell
Target host: https://labs.microgifter.com
Account model: existing Microgifter account system later
Billing model: visual only in Stage 1
Reward model: visual only in Stage 1
```

## Primary goal

Create the first buildable visual shell for `labs.microgifter.com` using the approved page mockups and asset packages.

The goal is to lock:

```text
layout
navigation
visual style
page structure
responsive behavior
public-to-app flow
admin backend style
asset organization
```

## Recommended build branch

Recommended implementation branch:

```text
training-lab-stage-1-ui-shell
```

Alternative:

```text
local-quest-workspace
```

If a new implementation branch is created, it should start from the latest `local-quest-workspace` branch after this documentation package is complete.

## Recommended build location

Recommended repo folder for Stage 1:

```text
labs/
```

Suggested internal structure:

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
  app/
    index.php
    campaigns.php
    campaign-detail.php
    sequence-tasks.php
    proof-upload.php
    rewards.php
    wallet.php
  admin/
    index.php
    campaigns.php
    review-queue.php
  assets/
    css/
    js/
    img/
```

If the existing project structure requires a different location, document that before creating files.

## Stage 1 approved public pages

```text
/
/pricing
/how-it-works
/about
/team
/blog
/blog-article
/contact
/sign-up
/sign-in
/cart
/checkout
```

## Stage 1 approved participant app pages

```text
/app
/app/campaigns
/app/campaigns/{demo_campaign}
/app/sequence-tasks
/app/proof-upload
/app/rewards
/app/wallet
```

These pages may use static demo data only.

## Stage 1 approved admin pages

```text
/admin
/admin/campaigns
/admin/review-queue
```

Do not create unique admin pages for every backend section during Stage 1. The remaining admin sections should inherit from the three approved admin UI patterns: Admin Overview, Admin Campaigns, and Admin Review Queue.

## Approved asset packages

Use the final asset packages created during planning:

```text
training-lab-final-marketing-assets-complete.zip
training-lab-final-app-pages-complete.zip
training-lab-final-admin-backend-assets.zip
```

These should inform page structure and visual direction.

## Approved design language

```text
minimal white layout
dark forest green primary actions
soft mint accents
rounded cards
clean line-art illustrations
simple tables
clear status pills
story-first copy
responsive desktop/mobile behavior
```

## Stage 1 allowed work

Stage 1 may create:

```text
static PHP/HTML pages
shared layout includes
shared header/nav/sidebar components
shared CSS
small UI-only JavaScript
static demo data arrays
image asset folders
placeholder links
responsive layout rules
non-functional forms
visual buttons and modals
```

Stage 1 may include demo UI states:

```text
sample campaign cards
sample proof submission cards
sample review queue rows
sample reward progress
sample wallet/reward cards
sample cart and checkout summary
sample admin metrics
```

## Stage 1 forbidden work

Do not create or modify:

```text
real payment processing
subscription billing
coupon logic
plan enforcement
real database tables
SQL install scripts
real file uploads
proof media storage
real reward issuing
wallet balance changes
claim/redeem processing
new standalone account system
production DNS or hosting config
existing Loyalty Quest runtime files
```

Do not wire forms to production actions. Do not create irreversible data-changing behavior.

## Account integration rule

Training Lab must integrate with the existing Microgifter account system later.

Stage 1 should not create a separate account system.

Approved Stage 1 behavior:

```text
visual sign-up page
visual sign-in page
static logged-in demo state
placeholder account menu
placeholder admin access state
```

Future implementation should use the main Microgifter account/session system.

## Hosting rule

The product target is:

```text
https://labs.microgifter.com
```

Stage 1 should prepare route and asset assumptions around the labs subdomain, but should not make DNS, server, SSL, or production deployment changes unless explicitly approved.

## Commerce rule

Pricing/cart/checkout are visual in Stage 1.

Approved Stage 1 behavior:

```text
pricing cards
monthly/annual visual toggle if simple
cart summary
checkout form UI
checkout success placeholder if added
receipt placeholder if added
```

Forbidden in Stage 1:

```text
real payments
Stripe/PayPal integration
subscription creation
billing records
webhooks
invoice creation
```

## Reward rule

Rewards are visual in Stage 1.

Approved Stage 1 behavior:

```text
reward progress cards
locked/unlocked visual states
sample reward issue status
sample wallet cards
sample claim status
```

Forbidden in Stage 1:

```text
real reward issuing
wallet balance updates
claim code creation
redeem logic
merchant payout logic
```

## Proof upload rule

Proof upload is visual in Stage 1.

Approved Stage 1 behavior:

```text
proof upload UI
file picker visual placeholder
sample uploaded proof card
sample review status
```

Forbidden in Stage 1:

```text
real file upload handling
server-side media storage
media validation
computer vision review
AI proof scoring
```

## Admin rule

Admin pages should be static UI shells in Stage 1.

Approved Stage 1 behavior:

```text
admin dashboard with static metrics
campaign table with static rows
review queue with static proof rows
visual approve/request changes buttons
```

Forbidden in Stage 1:

```text
real approval actions
real status changes
real permissions logic
real review history writes
```

## Navigation rule

The first build should make the product feel connected:

```text
public landing -> sign up / pricing
pricing -> cart -> checkout
sign in -> app dashboard
app dashboard -> campaigns -> campaign detail -> sequence/tasks -> proof upload
app dashboard -> rewards -> wallet
admin dashboard -> campaigns -> review queue
```

Links may be static placeholders where the target page is not part of Stage 1.

## Completion criteria

Stage 1 is complete when:

```text
all approved public pages exist
all approved participant app pages exist
all approved admin pages exist
shared style system is in place
navigation works between static pages
pages are responsive enough for desktop and mobile review
no production backend behavior is added
no payment/account/reward/upload logic is added
```

## Build-stop rule

If implementation begins and a requested change touches backend logic, database writes, real account changes, real uploads, real payments, or real rewards, stop and ask for explicit approval before continuing.
