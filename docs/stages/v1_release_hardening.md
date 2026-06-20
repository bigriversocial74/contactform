# V1 Release Hardening

V1 Release Hardening converts the completed focused V1 lifecycle into a production-verifiable release candidate.

## Scope

This package closes the audit gaps left after Stage F:

1. Run Playwright browser smoke from the active repository root on pull requests and pushes to `main`.
2. Exercise the published-product → cart → checkout-session browser path and verify secure Stripe redirection.
3. Make critical and high product-to-PPPM golden-path findings fail the Recovery Baseline.
4. Make launch readiness consume the complete canonical migration manifest.
5. Require Stripe platform configuration and ready Connect accounts for merchants with published products.
6. Provide a protected manual workflow that calls the real Stripe test API without the deterministic stub.
7. Mark the nested `microgifter-main/` recovery copy as non-authoritative.
8. Reconcile V1 route and source-of-truth documentation with the Stage F payment authority.

## Automated pull-request gates

### Recovery Baseline

The Recovery Baseline continues to apply the complete MySQL schema and run the full behavior and PHPUnit suites. The product-to-PPPM audit now has a gating wrapper:

```text
composer audit-product-pppm-golden-path-gate
```

Critical and high findings fail. A legacy claim-credential observation is superseded only when the canonical direct merchant-location redemption check passes in the same report.

### Browser Validation

The active root workflow runs Chromium through Playwright against PHP 8.3 and MySQL 8. The V1 smoke journey verifies:

- safe rendering of published product content;
- immutable product-version identity sent to the cart;
- cart drawer and cart-page projection;
- frozen checkout draft creation;
- pending order creation;
- provider checkout-session creation;
- redirect only to a secure hosted Checkout URL.

Database-backed validators remain responsible for ledger, issuance, ownership, redemption, and replay behavior. Browser smoke verifies the customer-facing wiring to those authorities.

## Stripe production readiness

`mg_payment_readiness()` now distinguishes platform readiness from launch readiness. Launch readiness additionally checks every merchant that currently owns a published product. Each must have an active Stripe account with charges and payouts enabled for the selected mode.

Both the administrator operations endpoint and the CLI launch-readiness command report:

```text
stripe_platform
stripe_selling_merchants
```

A production environment fails readiness when Stripe is not the active provider.

## Real Stripe test-provider evidence

The protected `Stripe Test Integration` workflow requires repository secrets for:

```text
STRIPE_TEST_PUBLISHABLE_KEY
STRIPE_TEST_SECRET_KEY
STRIPE_TEST_WEBHOOK_SECRET
STRIPE_TEST_CONNECTED_ACCOUNT_ID
```

It disables `MG_STRIPE_TEST_STUB`, retrieves the real platform and connected accounts, creates a destination-charge Checkout Session with a 15% application fee, expires the unused session, and uploads the JSON result as release evidence.

This provider-boundary workflow does not replace the staging purchase test. Before production approval, an operator must still complete an actual Stripe test Checkout and confirm the delivered webhook drives one paid-order fulfillment.

## Required staging evidence

The release remains unapproved until staging records:

- exact Git commit and deployment artifact;
- current restorable database backup;
- rollback artifact and procedure;
- clean canonical migration status;
- browser smoke pass;
- Stripe platform and selling-merchant readiness pass;
- real Stripe test-provider boundary pass;
- actual hosted Checkout and signed webhook completion;
- one-time ledger, receipt, PPPM, Microgift, Action Center, and notification fulfillment;
- send/regift, Follow Up, and merchant-location redemption completion;
- no unresolved SEV1 or SEV2 incident.

## Completion definition

V1 Release Hardening is complete when the pull request is green, the merge commit is green, and all required staging evidence is attached to an approved Stage 18 release record. No production merge or deployment is implied by the code package alone.
