# Campaign Recipe Review Cards

## Purpose
Upgrades the Merchant Agent Approval Queue so creative campaign recipe drafts are displayed as structured review cards instead of generic plan items.

## Added
- `/api/merchant/agent-approval-recipes.php`
  - Returns creative draft payload details keyed by approval id.
  - Reads existing `ai_merchant_plans` and `ai_merchant_plan_items` only.
- Approval queue JavaScript now merges recipe details into approval items.
- Recipe cards display:
  - draft type / label
  - campaign type
  - reward type
  - recipe key
  - channel package
  - draft copy / instructions
  - draft artifacts
  - generated blocks
  - edit/send-back links
- Approval actions remain approval-first:
  - Approve draft
  - Archive / decline
  - Defer
  - Convert to follow-up task when supported

## SQL
No SQL required.

## Safety
No publishing, sending, reward creation, or campaign activation happens from this UI. Approval decisions continue to use the existing Agent Review queue flow.
