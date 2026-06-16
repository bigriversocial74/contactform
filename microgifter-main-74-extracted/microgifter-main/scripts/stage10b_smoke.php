<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') exit(1);

$root = dirname(__DIR__);
$files = [
    'database/stage_10b_location_claim_authority.sql',
    'api/microgifts/_location_claim_authority.php',
    'tests/phpunit/Stage10BLocationClaimAuthorityTest.php',
    'docs/stages/stage_10b_location_claim_authority.md',
];

foreach ($files as $file) {
    if (!is_file($root . '/' . $file)) {
        throw new RuntimeException('Missing Stage 10B artifact: ' . $file);
    }
}

echo "Stage 10B smoke validation passed.\n";
