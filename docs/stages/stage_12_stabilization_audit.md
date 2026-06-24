# Stage 12 Stabilization Audit

Branch: `stage12-wallet-pppm-action-center-bridge`

## Current score

Overall readiness: **72 / 100**

This branch has strong feature coverage but is not merge-ready until runtime testing and conflict cleanup are complete.

| Area | Score | Notes |
| --- | ---: | --- |
| Merchant CRM core | 78 | Contacts, CRM events, profile follows, messages, timeline, and direct gifts are wired. Needs browser QA and schema install verification. |
| Campaign follow-ups | 74 | Rules, jobs, queue UI, worker, and migration exist. Needs worker execution test and delivery queue test. |
| Messaging/header integration | 76 | Header routes CRM threads to Merchant CRM for merchant owners and normal Messages for users. Needs end-to-end notification test. |
| Claim/redeem lifecycle | 75 | Claim and redeem now call lifecycle automation. Needs idempotency and duplicate-claim tests. |
| Schema/migrations | 67 | Merchant CRM and follow-up migrations exist. Some supporting delivery/suppression tables still rely partly on runtime installers. |
| Code maintainability | 58 | Many files were compacted into dense one-line PHP/JS. Functional review is possible, but future maintainability needs formatting pass. |
| Merge readiness | 45 | Branch is heavily diverged from `main`. Rebase/merge conflict pass is required before PR. |

## Stabilization fixes completed in this pass

1. **Claim automation is now inline**
   - `api/account/wallet-claim.php` now imports `_wallet_lifecycle_automation.php` and returns `lifecycle_automation` in the claim response.
   - Removed the temporary post-claim automation endpoint.

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

## High-risk files to lint first

Run PHP lint on these before any merge:

```bash
php -l api/account/wallet-claim.php
php -l api/merchant/wallet-redeem.php
php -l api/rewards/_wallet_lifecycle_automation.php
php -l includes/merchant-crm.php
php -l api/merchant/crm-message.php
php -l api/merchant/crm-messages.php
php -l api/merchant/crm-send-gift.php
php -l api/public/campaigns/signup.php
php -l api/public/campaigns/contest-entry.php
php -l api/public/campaigns/qr-pickup.php
php -l api/public/campaigns/_followups.php
php -l api/communications/campaign-followup-worker.php
php -l api/communications/_delivery.php
```

## Runtime QA checklist

### Setup

- Apply `database/stage_12_merchant_crm.sql`.
- Apply `database/stage_12_campaign_followups.sql`.
- Ensure message delivery tables exist by applying the delivery migration or running the delivery installer endpoint in a controlled setup step.
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

## Known remaining risks

- Branch is very diverged from `main`; merge conflicts are likely.
- Several new files are dense/minified PHP or JS and should be reformatted before long-term maintenance.
- Follow-up scheduling inside campaign transactions is non-fatal if tables are missing, so migrations must be applied before launch.
- Delivery/suppression schema still needs one production migration pass, not only runtime installers.
- Runtime tests have not been executed in this audit.

## Recommendation

Do not add more feature surface until:

1. PHP lint passes for high-risk files.
2. Database migrations apply cleanly on a staging database.
3. End-to-end campaign → wallet → claim → redeem → CRM → follow-up → notification flow passes.
4. Branch is rebased or merged against current `main` and conflicts are resolved.
