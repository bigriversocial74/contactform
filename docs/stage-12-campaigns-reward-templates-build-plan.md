# Stage 12 — Campaigns + Reward Templates Build Plan

## Purpose

Stage 12 upgrades Microgifter from a gift-only flow into a CRM-powered local rewards system.

The existing Microgifter inbox, sent, claim, and redemption features already behave like a local value wallet. Stage 12 adds the merchant tools that create wallet-ready reward items from newsletter signups, contests, QR scans, manual sends, and future agentic discovery integrations.

## Core product model

```text
Reward Template
  -> Campaign Trigger
  -> Wallet Item / Inbox Delivery
  -> Claim
  -> Redemption
  -> Demand Intelligence
```

### Reward Template

A reusable merchant-created local value object.

Examples:

- $10 Coffee Credit
- Free Appetizer
- 20% Off Lunch
- Dinner for Two Contest Prize
- VIP Event Perk
- Free Intro Class

### Wallet Item

An issued instance of a Reward Template in a user's Microgifter inbox/wallet.

Each Wallet Item tracks source and lifecycle state:

```text
issued
viewed
claimed
redeemed
expired
cancelled
```

### Campaign

A merchant automation that sends a reward into a user inbox when an action happens.

Example campaign sources:

- newsletter signup
- contest entry
- contest winner
- QR scan
- referral
- birthday club
- manual send
- agent discovery
- public API issue

## MVP scope

Stage 12 should stay focused. The first working loop is:

```text
Merchant creates reward template
Merchant creates signup campaign
Customer submits form
Reward lands in customer inbox
Customer claims reward
Merchant sees campaign activity
```

### Included

1. Reward Template builder
2. Campaign builder
3. Newsletter Signup campaign
4. Contest / Giveaway campaign
5. QR Reward Drop campaign
6. Campaign dashboard
7. Wallet item source tracking
8. Basic contact capture
9. Agent-discoverable toggle foundation

### Not included yet

- complex CRM segmentation
- email sequence automation
- full external app marketplace
- full agent API onboarding
- advanced analytics charts
- POS integration
- payment flow changes

## Merchant create menu changes

The current create modal should add a Campaign entry point instead of making every trigger a top-level item.

Recommended cards:

```text
Microgift
Create a prepaid local gift.

Campaign
Create forms, contests, QR drops, and reward automations.

Agent Offer
Publish an offer agents can discover and add to wallets.

Post
Publish an update to your public feed.

Storefront
Configure your public merchant storefront.

Add Location
Add a merchant claim and redemption location.
```

Campaign opens a second-step picker:

```text
Newsletter Signup
Contest / Giveaway
QR Reward Drop
Referral Reward
Birthday / VIP Reward
Agent-Discoverable Offer
```

## Data model draft

### reward_templates

```text
id
merchant_id
location_id nullable
title
description
reward_type
value_type
value_amount
currency
redemption_instructions
expiration_rule
quantity_limit
per_user_limit
agent_discoverable
status
created_at
updated_at
```

Reward types:

```text
dollar_credit
free_item
discount
perk_upgrade
event_reward
custom
```

### campaigns

```text
id
merchant_id
reward_template_id
campaign_type
title
description
status
starts_at nullable
ends_at nullable
quantity_limit nullable
per_user_limit nullable
requires_location_id nullable
agent_discoverable
created_at
updated_at
```

Campaign types:

```text
newsletter_signup
contest_giveaway
qr_reward_drop
referral_reward
birthday_vip
agent_offer
```

### campaign_contacts

```text
id
merchant_id
campaign_id
user_id nullable
email
phone nullable
name nullable
source
metadata_json
created_at
updated_at
```

### wallet_items

```text
id
user_id nullable
contact_id nullable
merchant_id
reward_template_id
campaign_id nullable
source_type
source_id nullable
status
issued_at
viewed_at nullable
claimed_at nullable
redeemed_at nullable
expires_at nullable
metadata_json
```

Source types:

```text
purchase
manual_send
newsletter_signup
contest_entry
contest_winner
qr_scan
agent_discovery
api_issue
```

### campaign_events

```text
id
merchant_id
campaign_id
wallet_item_id nullable
contact_id nullable
event_type
event_context_json
created_at
```

Event types:

```text
campaign.created
campaign.published
form.submitted
contest.entered
contest.winner_selected
qr.scanned
wallet_item.issued
wallet_item.claimed
wallet_item.redeemed
agent.offer_discovered
agent.wallet_add_requested
```

## Builder flows

### Reward Template builder

Steps:

1. What are you giving?
2. Where can it be used?
3. Who can claim it?
4. How many can be issued?
5. When does it expire?
6. Should agents be allowed to discover it?

### Newsletter Signup campaign

```text
Form submission
  -> contact created
  -> wallet item issued
  -> customer receives inbox reward
  -> merchant sees signup, issued, claimed, redeemed
```

Fields:

- campaign title
- form headline
- form description
- required fields
- attached reward template
- expiration rule
- quantity limit
- success message

### Contest / Giveaway campaign

```text
Entry submitted
  -> contact created
  -> optional entry reward issued
  -> winner selected
  -> prize reward issued
```

Fields:

- contest title
- entry form fields
- entry reward optional
- prize reward
- start/end dates
- winner selection method
- terms text

### QR Reward Drop campaign

```text
QR scan
  -> offer page
  -> add to wallet
  -> claim/redeem
```

Fields:

- campaign title
- attached reward template
- location/event context
- quantity limit
- one-per-user toggle
- QR code export

## Agent accessibility foundation

Stage 12 should not build the full agent marketplace yet. It should prepare reward objects for later search-related discovery.

Add fields and rules now:

```text
agent_discoverable
agent_summary
agent_categories
agent_locations
agent_budget_hint
agent_use_cases
agent_add_to_wallet_allowed
agent_gift_send_allowed
```

Future APIs:

```text
GET /api/public/v1/offers/search
GET /api/public/v1/offers/{id}
POST /api/public/v1/wallet/add
POST /api/public/v1/gifts/send
```

Permission levels:

```text
public_discovery
user_wallet_read
user_wallet_add
purchase_or_send
```

## Dashboard requirements

Campaign list columns:

```text
Campaign
Type
Reward
Status
Signups / Entries / Scans
Issued
Claimed
Redeemed
Conversion
```

Campaign detail metrics:

```text
contacts captured
wallet items issued
claim rate
redemption rate
estimated future demand
source breakdown
recent events
```

## Acceptance criteria

Stage 12 is complete when:

1. Merchant can create a Reward Template.
2. Merchant can create a Newsletter Signup campaign attached to that reward.
3. Public form submission creates or updates a contact.
4. Form submission issues a Wallet Item into the user/contact inbox.
5. Wallet Item preserves source_type and campaign_id.
6. User can claim the reward through the existing inbox/claim flow.
7. Merchant can view campaign activity and reward lifecycle status.
8. Contest entry and QR reward drop use the same Reward Template and Wallet Item engine.
9. Agent-discoverable fields exist but full agent API can remain behind a later stage.

## Suggested implementation order

1. Add Reward Template storage and admin builder.
2. Add Wallet Item source tracking.
3. Add Campaign storage and dashboard shell.
4. Build Newsletter Signup campaign end-to-end.
5. Build Contest / Giveaway campaign.
6. Build QR Reward Drop campaign.
7. Add agent-discoverable fields and validation.
8. Add tests and validator coverage.

## Naming note

Internally, use this model:

```text
Reward Templates are what merchants create.
Wallet Items are what users receive.
Campaigns are how Wallet Items get distributed.
The Microgifter inbox is the wallet.
Agents search and route approved objects into the wallet.
```
