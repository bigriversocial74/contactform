# Stage 12 Stabilization Audit

Branch: `stage12-wallet-pppm-action-center-bridge`

## Current score

Overall readiness: **84 / 100**

This branch now has stronger release gates and migration coverage. It is still not a 10/10 until the focused CI workflow passes, migrations apply on staging, runtime QA passes, and the branch is reconciled with `main`.

| Area | Score | Notes |
| --- | ---: | --- |
| Merchant CRM core | 84 | Contacts, CRM events, profile follows, messages, timeline, direct gifts, lifecycle-stage preservation, and dedupe are wired. Needs browser QA. |
| Campaign follow-ups | 84 | Rules, jobs, queue UI, worker, migration, and focused lint workflow exist. Needs worker execution and delivery queue test. |
| Messaging/header integration | 82 | Header routes CRM threads to Merchant CRM for merchant owners and normal Messages for users. Needs end-to-end notification test. |
| Claim/redeem lifecycle | 84 | Claim and redeem now call lifecycle automation inline. CRM event dedupe and merchant/customer notifications are covered. Needs duplicate and idempotency tests. |
| Schema/migrations | 84 | Merchant CRM, follow-up, message delivery, callbacks, suppression, and campaign email suppression migrations now exist. Needs staging apply test. |
| Code maintainability | 64 | High-risk paths are lint-gated, but many new files are still dense compact PHP/JS and should be formatted after merge-risk drops. |
| Merge readiness | 45 | Branch remains heavily diverged from `main`. Rebase/merge conflict pass is required before PR. |

## Stabilization fixes completed

1. **Claim automation is now inline**
   - `api/account/wallet-claim.php` now imports `_wallet_lifecycle_automation.php` and returns `lifecycle_automation` in the claim response.
   - The temporary post-claim automation endpoint was removed.

2. **Redeem automation is wired**
   - `api/merchant/wallet-redeem.php` calls lifecycle automation for both bridged and legacy wallet redemption paths.

3. **Delivery validation cleaned up**
   - `api/communications/_delivery.php` now uses explicit `$validRecipient` validation instead of the previous brittle boolean expression.

4. **CRM lifecycle stage preservation added**
   - `includes/merchant-crm.php` now ranks lifecycle stages and avoids downgrading a customer/redeemer to a lower-value stage such as follower or lead.

5. **Wallet lifecycle CRM event dedupe added**
   - `api/rewards/_wallet_lifecycle_automation.php` checks for existing wallet lifecycle CRM events before writing another `reward.claimed` or `reward.redeemed` event.

6. **Follow-up schema migration added**
   - `database/stage_12_campaign_followups.sql` creates `campaign_followup_rules` and `campaign_followup_jobs` for production deployment.

7. **Message delivery and suppression migration added**
   - `database/stage_12_message_delivery_campaign_suppression.sql` creates `message_events`, `message_delivery_jobs`, attempts, provider callbacks, user suppression rules, and campaign email suppression rules.

8. **Focused Stage 12 validation workflow added**
   - `.github/workflows/stage12-crm-followups-validation.yml` runs PHP lint for high-risk CRM/follow-up endpoints, JS syntax checks for the CRM/header/follow-up panels, and migration presence checks.

## High-risk files now lint-gated

The focused workflow runs `php -l` against:

```bash
api/account/wallet-claim.php
api/merchant/wallet-redeem.php
api/rewards/_wallet_lifecycle_automation.php
api/rewards/_zero_value_bridge.php
api/communications/_delivery.php
api/communications/_email_worker.php
api/communications/campaign-followup-worker.php
api/communications/email-worker.php
api/merchant/campaign-followup-jobs.php
api/merchant/campaign-followups.php
api/merchant/campaign-timeline.php
api/merchant/crm-message.php
api/merchant/crm-messages.php
api/merchant/crm-send-gift.php
api/merchant/merchant-crm.php
api/public/campaigns/_email_suppression.php
api/public/campaigns/_followups.php
api/public/campaigns/_limits.php
api/public/campaigns/_outbound.php
api/public/campaigns/_security.php
api/public/campaigns/contest-entry.php
api/public/campaigns/engage.php
api/public/campaigns/qr-pickup.php
api/public/campaigns/signup.php
api/public/campaigns/unsubscribe.php
includes/merchant-crm.php
includes/merchant-crm-view.php
merchant-crm.php
```

It also runs `node --check` against:

```bash
assets/js/header-signals.js
assets/js/merchant-crm.js
assets/js/merchant-crm-messages.js
assets/js/stage12-campaign-followups.js
```

## Runtime QA checklist

### Setup

- Apply `database/stage_12_merchant_crm.sql`.
- Apply `database/stage_12_campaign_followups.sql`.
- Apply `database/stage_12_message_delivery_campaign_suppression.sql`.
- Ensure `stage_v1d_transfer_conversations.sql` has been applied so `message_threads.conversation_key` exists.
- Ensure `messages.moderation_status` exists if message thread endpoints rely on content moderation fields.

### Campaign path

1. Create active reward template.
2. Create active newsletter campaign attached to that template.
3. Create a follow-up rule for `form.submitted` at `1_hour`.
4. Submit public newsletter campaign form.
5. Confirm campaign contact exists.
6. Confirm Merchant CRM contact/event exists.
7. Confirm wallet item exists.
8. Confirm follow-up job exists.
9. Run follow-up worker.
10. Confirm delivery job or notification was created.

### Claim path

1. Create/sign in as recipient user with matching email.
2. Confirm wallet item is visible.
3. Claim wallet item.
4. Confirm response includes `lifecycle_automation`.
5. Confirm `reward.claimed` appears in Merchant CRM.
6. Confirm `wallet_item.claimed` follow-up jobs are scheduled when matching rule exists.
7. Confirm merchant receives notification.
8. Repeat claim and verify CRM event is not duplicated.

### Redeem path

1. Sign in as merchant.
2. Redeem claimed wallet item.
3. Confirm response includes `lifecycle_automation`.
4. Confirm `reward.redeemed` appears in Merchant CRM.
5. Confirm `wallet_item.redeemed` follow-up jobs are scheduled when matching rule exists.
6. Confirm merchant/customer notifications are created.
7. Repeat redemption and verify duplicate behavior is safe.

### CRM messages

1. Merchant sends direct message to CRM contact with account.
2. Customer sees header message badge and opens `/messages.php`.
3. Customer replies.
4. Merchant sees header message badge and opens `/merchant-crm.php?thread=...`.
5. Merchant replies from CRM Messages panel.
6. Customer receives notification/message update.

## 10/10 release gate

This branch reaches 10/10 only when all of the following are true:

1. Focused Stage 12 validation workflow passes.
2. Existing global PR validation passes.
3. All Stage 12 migrations apply cleanly to staging.
4. Campaign → wallet → account link → claim → redeem → CRM → follow-up → notification flow passes manually.
5. CRM message customer/merchant round-trip passes manually.
6. Failed follow-up retry/cancel flow passes manually.
7. Branch is rebased or merged against current `main` and conflict-free.
8. Dense compacted PHP/JS is either formatted or accepted as temporary with a follow-up cleanup issue.

## Known remaining risks

- Branch is very diverged from `main`; merge conflicts are likely.
- Several new files are dense/minified PHP or JS and should be reformatted before long-term maintenance.
- Runtime tests have not been executed in this audit.
- Focused validation workflow has been added but has not run in this chat.
- Staging database migration apply has not been tested in this chat.

## Recommendation

Do not add more feature surface until:

1. Focused Stage 12 validation passes.
2. Database migrations apply cleanly on a staging database.
3. End-to-end campaign → wallet → claim → redeem → CRM → follow-up → notification flow passes.
4. Branch is rebased or merged against current `main` and conflicts are resolved.
