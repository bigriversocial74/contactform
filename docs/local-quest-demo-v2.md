# Local Quest Demo Platform v2

Local Quest Demo Platform v2 turns the Public Distribution API proof into a guided third-party app demo.

## New pages

```text
examples/local-quest-rewards/start.php
examples/local-quest-rewards/api-examples.php
examples/local-quest-rewards/admin-developer-readiness.php
```

## Demo path

1. Open `start.php`.
2. Run installer / SQL setup if needed.
3. Review API settings in `developer-starter.php`.
4. Create or sign in as a participant.
5. Link the participant to Microgifter with sandbox linking or production consent.
6. Complete a QR/geolocation quest action.
7. Issue the mapped Microgift reward.
8. Open the wallet and refresh status.
9. Claim/report the reward with QR/geolocation evidence.
10. Verify signed webhooks in `webhook.php`.
11. Review admin readiness in `admin-developer-readiness.php`.

## Purpose

The v2 demo makes Local Quest easier to hand to a partner developer. It keeps the SQL/auth/security foundation intact and adds a guided launcher, copy-ready API examples, and an admin QA view for readiness review.

## What this proves

- A third-party app can own its local user, quest progress, QR/geolocation context, and wallet UX.
- Microgifter remains the system of record for credential scope, Distribution Program access, reward issuance, claim reporting, webhook events, and audit history.
- The Local Quest foundation can be cloned into new app flavors such as loyalty triggers, event check-in rewards, creator fan drops, venue passports, and sponsor treasure hunts.

## Review checklist

- `start.php` shows the full demo path and progress.
- `api-examples.php` provides copy-ready cURL examples for all core Public API calls.
- `admin-developer-readiness.php` gives an admin view of setup, linked users, reward issue, claim reporting, and webhook evidence.
- The existing `developer-starter.php`, `index.php`, `wallet.php`, and `webhook.php` continue to provide the core app flow.
