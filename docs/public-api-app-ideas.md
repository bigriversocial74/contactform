# Public Distribution API app ideas

These are small demo apps that can be built after the basic docs validation app works.

## 1. Local Quest Rewards

A simple challenge app where users complete an action and receive a merchant-approved Microgift.

### Example flow

1. User completes a local challenge.
2. App records an external event such as `quest.completed`.
3. Backend checks that the user has a linked Microgifter account.
4. Backend issues a reward using the Distribution API.
5. User receives the Microgift in their Microgifter INBOX.

### Why it proves the API

This app demonstrates external event-to-reward distribution with strong partner appeal for venues, events, local guides, and community campaigns.

### Minimum feature set

- Challenge list.
- Challenge completion form.
- Sandbox linked-account creation.
- Reward issue button.
- Reward status display.
- Webhook event log.

## 2. Loyalty Trigger Demo

A fake coffee shop, pizza shop, bar, or restaurant loyalty app that issues a Microgift after a visit, spend, referral, or win-back trigger.

### Example flow

1. User reaches visit number 5 or completes a referral.
2. App records an external event such as `loyalty.milestone`.
3. Backend sends one reward with an idempotency key based on the milestone.
4. Microgifter returns the reward status.
5. App shows the customer that the reward has been delivered.

### Why it proves the API

This connects Microgifter to a familiar merchant category and shows how local businesses can turn loyalty actions into prepaid demand.

### Minimum feature set

- Fake customer profile.
- Visit or milestone counter.
- Issue reward action.
- Duplicate/idempotency retry test.
- Reward status panel.
- Webhook event log.

## 3. Creator Fan Drop

A creator community demo where fans receive local rewards after joining a campaign or completing a simple engagement action.

### Example flow

1. Fan joins a creator campaign.
2. App records `fan.joined_drop`.
3. Backend issues a Microgift tied to the creator campaign.
4. Fan receives a local reward and the creator gets a simple campaign result log.

### Why it proves the API

This matches Microgifter's potential for artists, bands, local communities, and small branded networks.

### Minimum feature set

- Campaign landing page.
- Fan signup form.
- Reward issue action.
- Campaign webhook log.
- Simple reward-delivered confirmation.

## Recommended next build

Build `Local Quest Rewards` first. It is visual, easy to demo, and shows the clearest connection between a third-party app action and a local Microgift reward.
