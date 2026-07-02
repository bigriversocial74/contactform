# Merchant Agent Campaign Recipe Engine

## Purpose
Adds a reusable campaign recipe catalog for Merchant Agent Chat so creative campaign ideas can mix and match campaign types, reward types, channels, social interactions, newsletters, contests, QR/drop mechanics, and follow-up messages.

## Current campaign types recognized
- newsletter_signup
- contest_giveaway
- qr_reward_drop
- referral_reward
- birthday_vip
- agent_offer

## Suggested campaign types available to the agent
- social_engagement_challenge
- flash_drop
- prepurchase_campaign
- winback_campaign
- local_event_promo
- ugc_story_campaign
- loyalty_milestone
- partner_cross_merchant

## Current reward types recognized
- dollar_credit
- free_item
- discount
- perk_upgrade
- event_reward
- audio_pack
- media_pack
- custom

## Suggested reward types available to the agent
- bogo
- bundle_offer
- mystery_reward
- vip_access
- loyalty_stamp
- partner_reward
- prepurchase_credit
- membership_perk
- service_upgrade
- community_prize

## Draft save actions
Merchant Agent Chat cards can now be saved as:
- Social Draft
- SMS Draft
- Email Draft
- Newsletter Draft
- Contest Draft
- QR Drop Draft
- Flash Drop Draft
- Social Engagement Draft
- Campaign Draft
- Full Campaign Package
- Reward Copy Draft

## Grounding
The recipe catalog is passed into the Merchant Agent prompt alongside merchant account data, memory docs, website sources, feed posts, products, rewards, campaigns, and policy. The agent is instructed to prefer current executable campaign and reward types for review-ready drafts, while suggested types may be used as strategic labels until product UI support is added.

## Storage
No SQL required. Saved drafts continue to use the existing Agent Review queue through `ai_merchant_plans` and `ai_merchant_plan_items`.
