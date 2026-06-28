# Merchant Agent QA Console Checklist

## Purpose
Verify that the merchant agent stack is ready for live workflow QA after each merge.

## Setup
- Merchant agent chat, review bridge, result layer, notification digest, memory, and control-center PRs are merged.
- Stage 19C SQL has been imported.
- `MG_ANTHROPIC_API_KEY` is configured.
- Merchant account has access to `/merchant-agent-qa.php`.

## Test 1: Health console loads
1. Open `/merchant-agent-qa.php`.
2. Click `Run health check`.
3. Confirm the score, pass count, warnings, failures, health cards, workflow counts, and latest error panel load.

## Test 2: Required dependencies
Confirm checks for:
- Anthropic API key
- Claude model catalog
- Stage 19C tables
- Agent policy
- Agent memory
- Review queue
- Execution result layer
- Notification digest
- Demo permission lock

## Test 3: Workflow counts
1. Generate at least one chat message.
2. Send one card to review.
3. Approve one item.
4. Return to `/merchant-agent-qa.php`.
5. Confirm chat, review, executed, and digest counters update.

## Test 4: Latest error
1. Trigger or locate an agent failed/error event if available.
2. Confirm the latest error panel displays the event type, timestamp, and summary.
3. If no errors exist, confirm the empty state is clear.

## Test 5: Quick links
Confirm all quick links open:
- Agent Chat
- Review Queue
- Execution Center
- Notifications
- Controls

## Pass criteria
- No new SQL required.
- Health check API returns a complete status payload.
- QA page makes failures visible without opening database tools.
- Merchant can run the final post-merge QA script from one place.
