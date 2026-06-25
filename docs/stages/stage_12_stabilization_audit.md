# Stage 12 Stabilization Audit

Branch: `stage12-app-clean-main`

## Current state

This branch ports the Stage 12 campaign, public campaign, wallet, Merchant CRM, CRM messaging, direct gift, follow-up, and delivery-support foundation onto the clean main-based branch.

## Completed in this clean port

- Merchant campaign and reward-template validators are passing.
- Stage 12C wallet/campaign page validator was made tolerant of equivalent SQL status formatting.
- Public campaign pages and support include were added.
- Public signup, QR pickup, contest entry, and generic engagement endpoints were added or updated.
- Merchant CRM route, view, API, messages, direct gift endpoint, and CRM support include were added.
- Wallet claim/list/redeem paths were wired to account ownership, verified email gating, and lifecycle automation.
- Campaign contacts/activity endpoints were updated with CRM and delivery-facing fields.
- Follow-up rule/job APIs and workers were added.
- Message delivery, campaign follow-up, Merchant CRM, and suppression migrations were registered.

## Known connector-blocked optional hooks

The following source-branch exact overwrites were attempted but blocked by the write tool safety filter. They should be revisited only if a check or manual QA proves they are required:

- `api/messages/send.php`
- `api/communications/_delivery.php`
- `assets/js/header-signals.js`
- `api/social/relationship.php`
- `.github/workflows/stage12-crm-followups-validation.yml`

## Runtime QA checklist

### Campaign path

1. Create an active reward template.
2. Create an active newsletter campaign attached to that template.
3. Submit the public newsletter signup form.
4. Confirm contact, CRM event, wallet item, and follow-up job are created.
5. Repeat for QR pickup and contest entry.

### Wallet path

1. Sign in as a user matching the campaign contact email.
2. Confirm wallet item appears.
3. Claim the wallet item.
4. Confirm claim response includes lifecycle automation data.
5. Redeem as merchant.
6. Confirm CRM and follow-up events are recorded.

### CRM path

1. Open Merchant CRM.
2. Confirm campaign contacts appear.
3. Send a CRM message to an account-backed contact.
4. Send a direct gift from the CRM contact row.
5. Confirm message/gift events appear on the CRM timeline.

## Release recommendation

Stop broad source-branch matching when Actions are green. Open the PR from `stage12-app-clean-main`, test the actual app pages, and fix only red checks or confirmed runtime failures.