# Recipe Draft Execution Adapter

## Purpose
Approved recipe drafts from the Agent Review queue now create reviewable draft artifacts in the correct merchant work areas.

## What happens on approval
The adapter detects AI plan payloads with:

```txt
source = merchant_agent_chat_creative_draft
```

Then it routes approved recipe drafts into safe draft outputs:

- Newsletter / email / SMS draft → Agent Message Draft Outbox
- Contest / QR drop / flash drop / social engagement / campaign draft → Campaigns as draft campaigns
- Reward copy draft → Reward Templates as draft rewards
- Full Campaign Package → grouped draft artifacts for campaign + reward + messages

## Safety
Nothing is published, activated, or customer-delivered automatically.

The adapter only creates draft records/events after merchant approval:

- campaigns stay `draft`
- reward templates stay `draft`
- message drafts stay in the Agent Message Draft Outbox
- social/feed style artifacts are recorded as review events and/or message drafts

## SQL
No SQL required. The adapter reuses:

- `campaigns`
- `reward_templates`
- `campaign_events`
- existing AI plan review tables

## Files
- `includes/ai/merchant-recipe-draft-actions.php`
- `api/merchant/agent-approval-action.php`
