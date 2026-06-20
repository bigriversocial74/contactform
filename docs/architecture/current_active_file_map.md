# Current Active File Map

This file records the active Microgifter runtime and release-validation sources after the completed Stage 1–18 foundation and focused V1 Stages A–F.

## Source of truth

GitHub repository:

```text
bigriversocial74/contactform
```

The repository root is the only active runtime source. Root PHP pages, `api/`, `includes/`, `assets/`, `database/`, `scripts/`, `tests/`, and `.github/workflows/` are canonical.

The nested `microgifter-main/` directory is an archived recovery copy. It must not be deployed, imported, used as a workflow source, or treated as current implementation evidence.

## Focused V1 public and customer pages

```text
index.php
discover.php
product.php
store.php
cart.php
checkout.php
checkout-success.php
inbox.php
sent.php
claimed.php
```

## Focused V1 merchant and administrative pages

```text
build.php
merchant.php
merchant-products.php
merchant-product.php
merchant-storefront.php
merchant-locations.php
merchant-claims.php
merchant-payments.php
admin-payments.php
admin/operations.php
```

## Canonical V1 authorities

```text
api/catalog/builder-draft.php
api/public/product.php
api/storefront/profile.php
api/commerce/cart-items.php
api/commerce/checkout-draft.php
api/commerce/orders.php
api/payments/order-checkout-session.php
api/payments/webhook.php
api/pppm/_ownership.php
api/microgifts/_action_center_projection.php
api/microgifts/_atomic_merchant_redemption.php
api/account/action-center-send.php
api/account/action-center-follow-up.php
api/merchant/microgift-claim.php
api/messages/_messaging.php
```

## Database source of truth

The ordered migration source of truth is:

```text
config/migrations.php
```

Clean installs, upgrades, readiness checks, and CI must consume that manifest. Focused V1 additions include:

```text
database/stage_v1c_checkout_session_intent_authority.sql
database/stage_v1d_transfer_conversations.sql
database/stage_v1f_stripe_payments.sql
```

Do not import individual migrations after applying a generated full-upgrade artifact unless the canonical runner reports them as pending.

## Active validation sources

```text
.github/workflows/pr-validation.yml
.github/workflows/recovery-baseline.yml
.github/workflows/browser-validation.yml
.github/workflows/stripe-test-integration.yml
scripts/recovery_baseline.sh
scripts/validate_product_pppm_golden_path_gate.php
scripts/validate_launch_readiness.php
scripts/validate_stage_f_stripe_behavior.php
scripts/validate_stripe_test_provider.php
```

Pull-request validation covers clean MySQL installation, migrations, PHP syntax, security, behavior validators, the complete PHPUnit suite, and active-root Playwright browser smoke. Real Stripe test-provider validation runs manually with protected repository secrets and produces release evidence.

## Current runtime profile

HostGator staging remains compatible with:

```text
MG_RUNTIME_PROFILE=hostgator
MG_ENABLE_POLLING_NOTIFICATIONS=true
MG_ENABLE_DB_OUTBOX=true
MG_ENABLE_QUEUE_WORKER=false
MG_ENABLE_REDIS=false
MG_ENABLE_WEBSOCKETS=false
MG_ENABLE_SSE=false
```

Stripe staging additionally requires the test-mode publishable key, secret key, webhook signing secret, HTTPS application URL, and a ready Express connected account for every merchant with a published product.

## Next implementation stage

Current package:

```text
V1 Release Hardening
```

Completion requires:

1. Active-root browser validation green on the pull request and merge commit.
2. Critical and high product-to-PPPM golden-path findings blocked in CI.
3. Canonical migration, Stripe platform, webhook, and selling-merchant readiness enforced by release checks.
4. Real Stripe test-provider evidence recorded from the protected manual workflow.
5. Staging deployment, backup evidence, rollback evidence, and end-to-end checkout, issuance, transfer, and redemption verification.
