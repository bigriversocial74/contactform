# Merchant Agent Result Layer QA

## Purpose
Verify the merchant agent workflow after approval creates visible result cards and resource links.

## Required setup
- Stage 19C SQL imported.
- Merchant user has merchant AI review/manage permissions.
- `MG_ANTHROPIC_API_KEY` configured.
- At least one merchant workspace exists.

## Test 1: Chat card to result
1. Open `/merchant-agent-chat.php`.
2. Ask: `Review my campaigns and rewards.`
3. Confirm Claude returns at least one card.
4. Click `Send to Review Queue`.
5. Open `/merchant-agent-approvals.php`.
6. Confirm the item shows source context, risk, confidence, and plan item ID.
7. Approve the item.
8. Confirm success toast references the created result or execution center.
9. Open `/merchant-agent-execution.php`.
10. Confirm the result card appears with created resource metadata and open-result link.

## Test 2: One-click package
1. Open `/merchant-agent-chat.php`.
2. Click `Create 3-part package`.
3. Open `/merchant-agent-approvals.php`.
4. Confirm three AI plan items appear:
   - Campaign draft
   - Reward template draft
   - CRM follow-up task
5. Approve each item.
6. Open `/merchant-agent-execution.php`.
7. Confirm each approved item appears as a completed result card.
8. Confirm open-result links point to the correct merchant workspace pages.

## Test 3: Result states
1. Reject an AI plan item.
2. Confirm it no longer appears as pending approval.
3. Confirm the execution/result layer shows it as skipped or non-actionable.
4. Defer an AI plan item.
5. Confirm it appears as deferred/skipped and does not create a resource.

## Test 4: Demo permissions
1. Log in as normal merchant.
2. Confirm the demo toggle is not visible.
3. Request `/api/ai/merchant-agent-command.php?demo=1`.
4. Confirm the response is blocked.
5. Log in as `super_admin`.
6. Confirm the demo toggle is visible and demo state loads.

## Pass criteria
- No new SQL required.
- Approval queue clearly shows source context.
- AI plan approvals run through Stage 19C safe adapters.
- Execution Center shows created resource result cards.
- Result links open the expected merchant pages.
- Demo data is available only to `super_admin`.
