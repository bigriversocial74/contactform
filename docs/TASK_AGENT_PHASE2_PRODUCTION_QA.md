# Task Agent Phase 2 Production QA

This runbook validates the Birthday & Occasion task-agent flow after deployment. It covers context, reviewable plans, reminders, permission-safe memory, contextual action cards, minimal AI use, and one persistent chat canvas.

## Release requirements

A Phase 2 release is ready only when:

- The dedicated PHP 8.2 and PHP 8.3 production-QA jobs pass.
- The existing Unified Agent Chat Canvas and Multi-Agent Runtime Memory regressions pass.
- PHP and JavaScript syntax checks pass.
- Deterministic requests report `used_ai=false` and zero token usage.
- AI is used only for an explicit comparison, explanation, or personal-message request.
- No task-agent action purchases, sends, publishes, schedules delivery, charges, claims, redeems, or transfers anything.
- No new SQL migration is required.

## Production smoke test

Use a test account with one private contact, one upcoming birthday or important date, a budget, gift preferences, and an active Birthday & Occasion agent.

| Test | Example request or action | Expected result |
|---|---|---|
| Context overview | `Show my upcoming birthdays.` | Saved dates and action cards; `response_source=system_query`; `used_ai=false`. |
| Named recipient | `Show the gifting context for Sarah.` | Relationship, date, budget, preferences, plans, and reminders from stored data. |
| Missing information | Use a contact without a budget or preference. | Warning card requests only the missing information; no invented data. |
| Gift-plan draft | `Create a gift plan for Sarah's birthday.` then save the card. | Canonical `user_gifting_plans` draft; approval required; no purchase or send. |
| Reminder | Create a reminder from the card. | Canonical in-app reminder using the saved date and lead time. |
| Reminder status | Complete, dismiss, or cancel the reminder. | Only the selected user's reminder changes. |
| Memory preview | `Remember that I prefer local experiences under $75.` | Review card appears before persistence. |
| Memory save/search | Save the card, then ask `What do you remember about my budget?` | Agent-specific memory returned with no AI call. |
| Memory archive | Archive the saved memory item. | Item disappears from active memory and cannot be returned by search. |
| Local discovery | Open `Explore local gifts`. | Same-origin `/discover.php` link with approved query/location context. |
| Explicit synthesis | `Compare these gift ideas for Sarah and explain the best fit.` | At most one configured provider call; compact response; AI reason and tokens logged. |
| Personal message | `Write a thoughtful birthday message for Sarah.` | One synthesis call; no message is sent automatically. |
| AI unavailable | Temporarily use an unavailable provider configuration. | Safe local fallback; no write action; clear status. |
| Cross-agent isolation | Open another agent and search memory. | Memory and chat remain isolated by owner and agent ID. |
| Persistent canvas | Reload the selected agent. | Same active conversation returns; no ordinary new-thread control appears. |
| Mobile layout | Test a narrow viewport. | Composer remains visible above the safe-area inset and the canvas remains scrollable. |

## Sensitive-data checks

Attempt to save memory containing a password, API key, access token, claim code, card number, private key, email address, phone number, or street address. The request must be rejected before persistence.

Review an AI-backed response and confirm that model-generated cards can only use `none` or `seed_prompt`. They must not create save, purchase, send, reminder, payment, redemption, or external-link actions.

## Observability checks

For deterministic responses, verify the runtime audit includes:

- `response_source=system_query` or `system_action`
- `used_ai=false`
- `ai_tokens_total=0`

For a permitted AI response, verify the audit includes:

- `response_source=anthropic`
- a non-empty `ai_reason`
- selected model key
- input, output, and total token usage
- the agent and thread identifiers

## Rollback

If a production regression is found, redeploy the prior known-good `integration-from-repair-20260628` archive. This phase adds no schema migration, so rollback does not require database changes.
