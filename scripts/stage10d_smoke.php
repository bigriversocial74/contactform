<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') exit(1);

$root = dirname(__DIR__);
foreach ([
    'database/stage_10d_merchant_claim_operations.sql',
    'api/microgifts/_claim_operations.php',
    'api/merchant/microgift-claim.php',
    'api/merchant/microgift-claim-history.php',
    'api/admin/microgift-claim-escalations.php',
    'tests/phpunit/Stage10DMerchantClaimOperationsTest.php',
    'docs/stages/stage_10d_merchant_claim_operations.md',
] as $file) {
    if (!is_file($root . '/' . $file)) throw new RuntimeException('Missing Stage 10D artifact: ' . $file);
}

echo "Stage 10D smoke validation passed.\n";
