# Recipe Execution Result UI

## Purpose
Improves `/merchant-agent-execution.php` so approved recipe drafts show exactly what draft artifacts were created after execution.

## Added
The execution center now renders grouped created-artifact cards when an execution result includes a `resources` array.

Created artifact cards can show:
- campaign draft
- reward draft
- message draft
- social/feed style draft record
- full campaign package result

## Landing page links
When an execution creates a campaign draft, the UI now includes both:

- **Open campaign draft**
- **View landing page**

Landing page URL mapping:
- `newsletter_signup` -> `/newsletter-signup.php?campaign={campaign_public_id}`
- `contest_giveaway` -> `/contest.php?campaign={campaign_public_id}`
- `qr_reward_drop` -> `/qr-reward.php?campaign={campaign_public_id}`
- `referral_reward` -> `/referral-reward.php?campaign={campaign_public_id}`
- `birthday_vip` -> `/birthday-vip.php?campaign={campaign_public_id}`
- `agent_offer` -> `/agent-offer.php?campaign={campaign_public_id}`

The landing page may still require the campaign to be active/public before customers can use it; the link is included so the merchant can preview/share the correct destination once ready.

## SQL
No SQL required.

## Files
- `assets/js/merchant-agent-execution.js`
- `assets/css/merchant-agent-execution-results.css`
- `merchant-agent-execution.php`
