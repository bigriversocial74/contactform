# Creator Campaign Builder v1

## Purpose

This module creates one merchant-owned Brand–Creator Campaign system for approved Microgifter Creator users. UGC, affiliate-style promotion, referrals, signups, interactions, claims, redemptions, and sales commissions are compensation methods inside the same campaign agreement.

The legacy `marketing_affiliate` model is not used by this module.

## Route

- Merchant UI: `/merchant-creator-campaigns.php`
- Merchant API: `/api/merchant/creator-campaigns.php`
- SQL: `database/stage_12_creator_campaign_builder.sql`

The existing `/merchant-campaigns.php` page receives a Creator Campaigns launcher.

## Builder steps

1. Campaign details
2. Products and offers
3. Creator eligibility
4. UGC deliverables
5. Compensation rules
6. Attribution settings
7. Budget and limits
8. Content rights
9. Terms and disclosures
10. Review and publish

## Existing-system integration

- Uses the existing authenticated user and merchant workspace.
- Uses `merchant.manage` through the existing permission system.
- Uses the existing approved `creator` user model for participants.
- Loads merchant products, rewards, and campaign offers through the existing merchant product picker endpoint.
- Preserves CRM consent requirements.
- Writes standard audit and event records.
- Creates an immutable agreement snapshot when a campaign becomes `scheduled` or `active`.

## Campaign lifecycle

`draft → scheduled → active → paused → completed`

Exception/end states:

`cancelled → archived`

## Agreement versioning

Draft edits do not create agreement versions. Scheduling or activating a campaign requires merchant confirmation and creates a hash-addressed immutable agreement snapshot. Saving the same configuration does not create duplicate versions. Material changes create the next version.

## Database records

- `creator_campaigns`
- `creator_campaign_products`
- `creator_campaign_deliverables`
- `creator_campaign_compensation_rules`
- `creator_campaign_agreement_versions`
- `creator_campaign_participants`

Participant execution, creator applications, attribution events, earnings, disputes, and payout automation can build on this foundation without changing the builder contract.

## Deployment

Import:

```text
database/stage_12_creator_campaign_builder.sql
```

Then open:

```text
/merchant-creator-campaigns.php
```
