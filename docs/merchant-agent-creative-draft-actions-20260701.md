# Merchant Agent Creative Draft Actions

Adds creative draft save actions to Merchant Agent Chat.

## Actions
Agent response cards can be saved as:
- Social Draft
- SMS Draft
- Email Draft
- Campaign Draft
- Reward Copy Draft

## Storage
No new SQL is required. Drafts are saved into the existing Agent Review queue using `ai_merchant_plans` and `ai_merchant_plan_items` with review-ready suggested payloads.

## Review flow
Saved drafts remain approval-first. They are not published, sent, or activated automatically. Merchants review them from `/merchant-agent-approvals.php`.
