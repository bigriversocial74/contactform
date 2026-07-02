# Agent Chat Recipe Cards

## Purpose
Displays campaign recipe data directly inside Merchant Agent Chat, so recipe-aware agent outputs look like campaign builder cards before they are saved to the Agent Review queue.

## What renders in chat
Recipe-aware cards and blocks now show:
- campaign type
- reward type
- recipe key
- channel package
- draft copy / instructions
- draft artifacts

## Data sources
The renderer reads recipe fields from card-level values or `review_payload` values already produced by the Agent Campaign Recipe Engine:
- `recommended_campaign_type`
- `recommended_reward_type`
- `recipe_key`
- `channel_package`
- `draft_artifacts`
- `draft_type`
- `draft_label`
- `draft_title`
- `draft_body`

## Safety
This is display-only. Saving, approval, publishing, sending, reward creation, and campaign activation still use the existing approval-first flows.

## SQL
No SQL required.
