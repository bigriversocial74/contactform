# Merchant Agent Output Review Panel

Adds an Output Review tab to `/merchant-memory.php` so the latest Merchant Agent Chat result can be inspected without reading raw logs.

The panel displays the latest agent run with prompt preview, agent response preview, inferred creative preset label, selected model, model route fields, context profile, deep database flag, token counts, memory/source/feed/policy usage flags, feed post count, selected skills, thread id, card count, block count, and review-ready card count.

No private uploaded files are exposed. The panel reuses existing `ai_usage_events` metadata and `campaign_events` chat context.

SQL: none required.
