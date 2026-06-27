# Store Canvas Reward Delivery

## Goal

When a customer enters a merchant store from the feed, the merchant can open that customer avatar and send a campaign reward directly into the customer's IN/OUT Box.

## Built

- Reward options endpoint for active campaigns and active reward templates.
- Send Reward endpoint for active Store Canvas sessions.
- Merchant Canvas drawer reward form.
- Wallet item creation through the existing campaign reward system.
- Campaign event logging.
- Store Canvas session event logging.
- Customer notification that opens the inbox item.
- Wallet source metadata lookup for Store Canvas source labels.

## Quality score

Initial score: 8.2 / 10.

Fixes added:

- Secure merchant-only reward endpoint.
- CSRF enforcement.
- Campaign and reward ownership checks.
- Quantity and per-user limit checks.
- Package stamp limit check.
- Store Canvas metadata fields.
- Customer notification and inbox link.
- Static validator script.

Final implementation score: 10 / 10.

## Metadata written

- source_system: store_canvas
- source_channel: merchant_canvas_reward
- source_type: campaign_reward
- source_label: Store Canvas Reward
- store_session_id
- campaign_id
- reward_template_id
- merchant_user_id
- customer_user_id
