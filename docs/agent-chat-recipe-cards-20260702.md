# Agent Chat Recipe Cards — 2026-07-02

## Summary

This rebuild adds display-only campaign recipe cards directly inside Merchant Agent Chat.

The implementation starts from the latest `integration-from-repair-20260628` branch and avoids reusing the stale PR #633 branch. It preserves the existing approval-first workflow and only adds in-chat presentation of recipe-aware agent output.

## Files changed

- `merchant-agent-chat.php`
  - Loads `assets/css/merchant-agent-chat-recipe-cards.css`.

- `assets/js/merchant-agent-chat.js`
  - Detects recipe-aware cards and blocks in chat payloads.
  - Reads recipe values from card/block fields and from `review_payload`.
  - Supports `review_payload` when it is already an object or when it is a JSON string.
  - Renders structured in-chat recipe cards for:
    - campaign type
    - reward type
    - recipe key
    - channel package
    - draft copy / instructions
    - draft artifacts

- `assets/css/merchant-agent-chat-recipe-cards.css`
  - Adds scoped styling for the in-chat recipe card layout.

## Recipe fields supported

The chat renderer checks for the following values on the card/block itself and in `review_payload`:

- `recommended_campaign_type`
- `recommended_reward_type`
- `recipe_key`
- `channel_package`
- `draft_artifacts`
- `draft_type`
- `draft_label`
- `draft_title`
- `draft_body`

Fallbacks are also supported for older or adjacent payload shapes, including `campaign_type`, `reward_type`, `artifacts`, `copy`, `instructions`, `campaign_title`, and `why_this_recipe`.

## Safety

This is display-only.

It does not publish campaigns, activate campaigns, send messages, create rewards, or bypass merchant approval.

Existing controls remain unchanged:

- `Send to Review Queue`
- `In Review Queue`
- local session-only `Save draft`
- local session-only `Dismiss`

Approved recipe draft execution still stays behind the existing review/approval flow added in PR #638.

## SQL

None required.
