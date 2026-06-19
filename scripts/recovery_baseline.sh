#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${ROOT}"

composer validate --strict
php scripts/validate_migration_manifest.php
php scripts/build_full_upgrade_sql.php build/microgifter_full_upgrade.sql
php scripts/validate_stage10f_upgrade.php

find . \
  -path './vendor' -prune -o \
  -path './.git' -prune -o \
  -name '*.php' -type f -print0 \
  | xargs -0 -n1 php -l >/tmp/microgifter-php-lint.log

echo "PHP syntax validation passed."
php scripts/run_migrations.php

composer test-security
composer test-frontend-contracts
composer test-money-behavior
composer test-microgift-behavior
composer test-pppm-resend-behavior
composer audit-product-pppm-golden-path
composer test-tip-behavior
composer test-tip-payment-behavior
composer test-tip-recovery-behavior
composer test-subscription-recovery-behavior
composer test-public-profile-behavior
composer test-admin-dashboard-behavior
composer test-profile-editor-behavior
composer test-profile-moderation-behavior
composer test-storefront-product-management-behavior
composer test-profile-discovery-behavior
composer test-pppm-publish-distribution
composer test-engagement-mutations-behavior
composer test-social-feed-publishing-behavior
composer test-prepaid-demand-behavior
composer test-agent-strategy-control-behavior
composer test-agent-approval-center-behavior
composer test-demand-orchestration-behavior
composer test-demand-orchestration-operations
composer test-demand-orchestration-recovery
composer test-checkout-fulfillment
composer test-lifecycle-completion
composer test

echo "Recovery baseline passed."
