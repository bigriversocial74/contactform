<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') exit(1);

$root = dirname(__DIR__);
foreach ([
    'database/stage_10c_atomic_claim_redemption_inbox.sql',
    'api/microgifts/_atomic_merchant_redemption.php',
    'tests/phpunit/Stage10CAtomicClaimRedemptionInboxTest.php',
    'docs/stages/stage_10c_atomic_claim_redemption_inbox.md',
] as $file) {
    if (!is_file($root . '/' . $file)) throw new RuntimeException('Missing Stage 10C artifact: ' . $file);
}

echo "Stage 10C smoke validation passed.\n";
