# Merchant Agent Control Center QA

## Purpose
Verify the policy layer, memory controls, approval rules, and audit trail without adding SQL.

## Setup
- PRs through merchant agent memory are merged.
- Merchant account can access `/merchant-automation.php`.
- Stage 19C tables exist.

## Test 1: Load control center
1. Open `/merchant-automation.php`.
2. Confirm Agent Control Center appears below the automation KPI row.
3. Confirm policy rules, allowed actions, avoid actions, memory controls, and audit trail load.

## Test 2: Save policy
1. Change max risk level to `low`.
2. Set minimum confidence to `75`.
3. Select a smaller allowed-action set.
4. Add at least one avoid-action key.
5. Save policy.
6. Refresh and confirm values persist.
7. Confirm `campaign_events` contains `merchant.agent_policy.saved` and `merchant.agent_policy.audit`.

## Test 3: Memory controls
1. Save a preference from an agent card.
2. Return to `/merchant-automation.php`.
3. Confirm preference appears in memory controls.
4. Remove the preference.
5. Confirm it no longer appears in the control center.
6. Pause learning and confirm paused state appears.
7. Resume learning and confirm state clears.

## Test 4: Policy injected into chat
1. Set max risk level to low.
2. Add an avoid-action key.
3. Open `/merchant-agent-chat.php`.
4. Ask for recommendations.
5. Confirm the chat endpoint uses memory-aware policy context through `merchant_agent_policy`.

## Test 5: Audit trail
1. Save policy.
2. Remove memory item.
3. Pause/resume learning.
4. Confirm the audit trail displays the latest events.

## Pass criteria
- No new SQL required.
- Policy state is event-backed.
- Memory controls are event-backed.
- Claude receives both memory and policy context.
- All actions remain approval-first.
