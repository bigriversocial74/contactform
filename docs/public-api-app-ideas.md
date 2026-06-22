# Public Distribution API app ideas

These are demo apps that prove the Public Distribution API can power third-party reward experiences.

## 1. Local Quest Rewards — built first

Local Quest Rewards is implemented at:

```text
examples/local-quest-rewards/
```

It is a small local experience app where a user completes a quest and receives a merchant-approved Microgift.

### Example flow

1. User opens the app as a guest/demo user.
2. App assigns a stable `external_user_id`.
3. App creates a sandbox linked account.
4. User completes a local challenge.
5. App checks its local reward rule.
6. App issues a reward through the Public Distribution API.
7. App checks status and records webhook delivery.

### Implemented feature set

- Guest/demo user identity.
- Sandbox linked-account creation.
- Quest list.
- Quest completion.
- Quest-to-reward mapping.
- Local permission checks.
- Reward issue button.
- Reward status display.
- Webhook verification endpoint.
- Local JSON state.
- Event log.

## 2. Loyalty Trigger Demo

A fake coffee shop, pizza shop, bar, or restaurant loyalty app that issues a Microgift after a visit, spend, referral, or win-back milestone.

### Example flow

1. User reaches visit number 5 or completes a referral.
2. App records an external event such as `loyalty.milestone`.
3. Backend sends one reward with an idempotency key based on the milestone.
4. Microgifter returns the reward status.
5. App shows the customer that the reward has been delivered.

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

### Minimum feature set

- Campaign landing page.
- Fan signup form.
- Reward issue action.
- Campaign webhook log.
- Simple reward-delivered confirmation.

## Next recommendation

Use Local Quest Rewards to drive the Microgifter permission-system pass. The app now separates local app permission from Microgifter final authorization.
