# Training Lab Account System and Hosting Integration

## Purpose

This document defines how Training Lab by Microgifter should integrate with the existing Microgifter account system and where it should be hosted.

This is a planning document only. It does not approve implementation, DNS changes, authentication changes, billing changes, or production deployment.

## Hosting target

Training Lab should be hosted at:

```text
https://labs.microgifter.com
```

The labs subdomain should be treated as the public and logged-in home for Training Lab experiences.

## Product relationship

Training Lab is a Microgifter module, not a separate standalone account system.

Recommended product structure:

```text
Microgifter Account
  -> Training Lab
  -> Campaigns
  -> Sequences / Tasks
  -> Proof Uploads
  -> Action Receipts
  -> Rewards
  -> Wallet / Claim Status
```

## Account source of truth

The existing Microgifter account system should remain the source of truth for:

```text
user identity
login and sign up
merchant / organization ownership
admin roles
reviewer roles
participant identity
wallet access
reward ownership
claim status
billing/customer account later
```

Training Lab should not create a separate user table, password system, session system, wallet system, or billing identity layer.

## Training Lab module data

Training Lab can add its own module-specific records:

```text
training campaigns
campaign participants
sequences
tasks
proof submissions
admin reviews
action receipts
reward rules
reward issue records
streaks
training events
```

These records should reference existing Microgifter users/accounts wherever possible.

## Identity mapping

Recommended identity references:

```text
training_participants.user_id -> existing Microgifter user id
training_campaigns.owner_user_id -> existing Microgifter user id
training_campaigns.organization_id -> existing merchant / organization id when available
training_reviews.reviewer_user_id -> existing Microgifter user id
training_reward_issues.user_id -> existing Microgifter user id
```

If a table needs public-facing references, it should use `public_id` values rather than exposing numeric IDs in URLs.

## Roles and permissions

Training Lab-specific permissions should extend the existing account system.

Recommended role concepts:

```text
participant
campaign_owner
reviewer
organization_admin
platform_admin
```

These roles should not replace the main account system. They should be mapped from existing Microgifter roles, merchant/organization ownership, or an approved allowlist during the first build.

## First build simplification

For the first implementation phase, keep account integration simple:

```text
logged-in Microgifter user can access Training Lab
admin/reviewer permissions can use an existing role or config allowlist
participant identity maps to the existing logged-in user
wallet/reward integration can be stubbed or read-only until reward issuing is approved
pricing/cart/checkout can remain visual/static until commerce implementation is approved
```

## Labs subdomain session expectation

Because the product will be hosted on `labs.microgifter.com`, future implementation should decide how sessions are shared across Microgifter properties.

Open implementation decisions:

```text
Will labs.microgifter.com share auth cookies with microgifter.com?
Will sign-in redirect to the main Microgifter auth flow?
Will the account dashboard link into labs.microgifter.com?
Will labs.microgifter.com use the same database connection/config as the main app?
Will CORS or cross-subdomain CSRF handling be needed?
```

Recommended direction:

```text
Use the main Microgifter login/signup flow.
After login, redirect users back to labs.microgifter.com.
Keep Training Lab session behavior consistent with the main Microgifter app.
Avoid creating duplicate auth screens unless they submit to the same account system.
```

## Public and private areas

Suggested public pages on labs.microgifter.com:

```text
/
/pricing
/how-it-works
/about
/team
/blog
/contact
/sign-up
/sign-in
/cart
/checkout
```

Suggested logged-in app pages on labs.microgifter.com:

```text
/app
/app/campaigns
/app/campaigns/{campaign_public_id}
/app/tasks/{task_public_id}
/app/proof-upload/{task_public_id}
/app/rewards
/app/wallet
```

Suggested admin pages on labs.microgifter.com:

```text
/admin
/admin/campaigns
/admin/review-queue
```

Additional admin sections should inherit from the admin backend UI patterns instead of requiring a custom design for every section.

## Commerce relationship

The pricing/cart/checkout flow should eventually connect to the main Microgifter commerce/account system.

Recommended future flow:

```text
pricing page -> cart -> checkout -> checkout success -> receipt -> account billing record
```

First build rule:

```text
Do not implement real billing, payment processing, subscriptions, coupons, or plan enforcement until explicitly approved.
```

## Reward and wallet relationship

Training Lab rewards should eventually connect back to the main Microgifter wallet/reward system.

Recommended future flow:

```text
proof approved -> action receipt created -> reward rule evaluates -> reward issue record -> Microgifter wallet/reward record -> claim/redeem status
```

First build rule:

```text
Do not issue real rewards until the manual proof/review/action receipt loop is working and reward issuing is explicitly approved.
```

## What not to duplicate

Training Lab should not duplicate:

```text
login system
password reset system
user identity model
merchant account model
wallet model
payment/customer model
reward ownership model
claim/redeem model
```

It should extend those systems with Training Lab-specific campaign, proof, review, receipt, and streak data.

## Build readiness impact

Before implementation begins, confirm:

```text
labs.microgifter.com is the target host
main Microgifter auth is the source of truth
first build is static UI shell unless otherwise approved
no separate auth system will be created
no real billing or reward issuing in Stage 1
admin access uses existing role/allowlist for MVP
```
