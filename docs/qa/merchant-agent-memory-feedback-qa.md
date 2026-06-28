# Merchant Agent Memory + Feedback QA

## Purpose
Verify that merchant feedback is saved, visible in Agent Memory, and included in future agent context.

## Test 1: Chat feedback
1. Open `/merchant-agent-chat.php`.
2. Ask the agent for campaign or reward recommendations.
3. Confirm each chat card has feedback buttons.
4. Click `Useful` on one card.
5. Click `Save preference` on one card.
6. Confirm the Agent Memory panel updates counts and preference list.
7. Send another chat prompt and confirm the API uses the memory-aware chat sender.

## Test 2: Review queue feedback
1. Open `/merchant-agent-approvals.php`.
2. Confirm approval cards have feedback buttons.
3. Click `Too risky` on a recommendation.
4. Confirm the memory API stores a `merchant.agent_feedback.saved` event.

## Test 3: Execution/result feedback
1. Open `/merchant-agent-execution.php`.
2. Confirm execution result cards have feedback buttons.
3. Click `Already done` or `Not useful`.
4. Confirm memory counts update when returning to `/merchant-agent-chat.php`.

## Test 4: Avoid action type
1. On any agent card, click `Avoid this action`.
2. Confirm the memory panel shows the action under Avoid.
3. Ask the agent for new recommendations.
4. Confirm future recommendations should avoid similar action keys unless the agent explains why.

## Test 5: Audit trail
1. Query recent `campaign_events` for the merchant.
2. Confirm events exist for:
   - `merchant.agent_feedback.saved`
   - `merchant.agent_preference.saved`
   - `merchant.agent_avoid_action.saved`
   - `merchant.agent_memory.updated`

## Pass criteria
- No new SQL required.
- Feedback buttons appear on chat, approval, execution, and digest cards.
- Memory panel shows preferences, avoid actions, and feedback counts.
- Chat endpoint calls the memory-aware sender.
- Future Claude requests include `merchant_agent_memory`.
