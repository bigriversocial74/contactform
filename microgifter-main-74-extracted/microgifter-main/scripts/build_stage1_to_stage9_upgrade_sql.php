<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found.');
}

$root = dirname(__DIR__);
$databaseDir = $root . '/database';
$outputDir = $databaseDir . '/generated';
$outputPath = $outputDir . '/microgifter_stage1_to_stage9_upgrade.sql';

$files = [
    'stage_3_agent_persistence.sql',
    'stage_3_gift_activity_persistence.sql',
    'stage_3_gift_lifecycle.sql',
    'stage_3_merchant_claim_codes.sql',
    'stage_3_pppm_core.sql',
    'stage_3_pppm_activity_layer.sql',
    'stage_3_pppm_delivery_assignment.sql',
    'stage_4_product_asset_foundation.sql',
    'stage_4b_builder_persistence.sql',
    'stage_4c_feed_stream_storefronts.sql',
    'stage_4d_digital_fulfillment_media.sql',
    'stage_4e_distribution_external_inputs.sql',
    'stage_4f_future_demand_intelligence.sql',
    'stage_5a_merchant_workspace.sql',
    'stage_5c_storefront_management.sql',
    'stage_5d_merchant_pppm_operations.sql',
    'stage_5e_merchant_distribution_operations.sql',
    'stage_5f_merchant_intelligence_reporting.sql',
    'stage_5g_claim_operations.sql',
    'stage_5h_notifications_messaging_alerts.sql',
    'stage_5i_payments_checkout_reconciliation.sql',
    'stage_5j_foundation_reconciliation.sql',
    'stage_7b_money_engine.sql',
    'stage_8b_entitlements_library.sql',
    'stage_8c_entitlement_lifecycle.sql',
    'stage_9b_microgift_engine.sql',
    'stage_9c_microgift_lifecycle.sql',
    'stage_9d_microgift_operations.sql',
];

$forbiddenPatterns = [
    '/\bDROP\s+DATABASE\b/i' => 'DROP DATABASE',
    '/\bDROP\s+TABLE\b/i' => 'DROP TABLE',
    '/\bTRUNCATE\s+TABLE\b/i' => 'TRUNCATE TABLE',
    '/\bDELETE\s+FROM\s+`?(users|roles|permissions|role_permissions|user_roles|user_sessions)`?\b/i' => 'destructive identity delete',
    '/\bUPDATE\s+`?users`?\s+SET\b/i' => 'bulk user mutation',
];

$sections = [];
$sourceChecksums = [];
foreach ($files as $file) {
    $path = $databaseDir . '/' . $file;
    if (!is_file($path)) {
        throw new RuntimeException('Missing source SQL file: ' . $file);
    }
    $sql = file_get_contents($path);
    if (!is_string($sql) || trim($sql) === '') {
        throw new RuntimeException('Empty source SQL file: ' . $file);
    }
    foreach ($forbiddenPatterns as $pattern => $label) {
        if (preg_match($pattern, $sql) === 1) {
            throw new RuntimeException("Forbidden {$label} statement found in {$file}.");
        }
    }
    $sourceChecksums[$file] = hash('sha256', $sql);
    $sections[] = "\n-- ============================================================================\n"
        . "-- SOURCE: database/{$file}\n"
        . "-- SHA256: {$sourceChecksums[$file]}\n"
        . "-- ============================================================================\n\n"
        . rtrim($sql) . "\n";
}

$generatedAt = gmdate('Y-m-d\TH:i:s\Z');
$header = <<<'SQL'
-- Microgifter consolidated Stage 1 -> Stage 9 upgrade
--
-- TARGET DATABASE:
--   Existing early Stage 1 installation with working users/accounts/login.
--
-- PRESERVATION CONTRACT:
--   This file does not drop, truncate, delete, or recreate the existing identity
--   foundation. Existing users, roles, account memberships, sessions, profiles,
--   audit/security records, and delivery records are preserved.
--
-- REQUIRED DEPLOYMENT ORDER:
--   1. Export a full database backup.
--   2. Import this SQL file into the existing Microgifter database.
--   3. Upload and extract the latest merged repository ZIP.
--   4. Preserve server-only .env/config secrets and uploaded storage.
--   5. Run: php scripts/stage9e3_smoke.php
--   6. Confirm both existing accounts can still log in.
--
-- IMPORTANT:
--   Run this once against the exported early Stage 1 schema reviewed for this build.
--   The source migrations are additive and use CREATE TABLE IF NOT EXISTS and
--   INSERT IGNORE where appropriate. A failed import must be investigated before
--   retrying or uploading application code.

SET NAMES utf8mb4;
SET @MG_OLD_FOREIGN_KEY_CHECKS := @@FOREIGN_KEY_CHECKS;
SET @MG_OLD_UNIQUE_CHECKS := @@UNIQUE_CHECKS;
SET FOREIGN_KEY_CHECKS = 0;
SET UNIQUE_CHECKS = 0;

SQL;

$verification = <<<'SQL'

-- ============================================================================
-- POST-UPGRADE VERIFICATION
-- ============================================================================

SET FOREIGN_KEY_CHECKS = @MG_OLD_FOREIGN_KEY_CHECKS;
SET UNIQUE_CHECKS = @MG_OLD_UNIQUE_CHECKS;

SELECT 'microgifter_stage1_to_stage9_upgrade_complete' AS upgrade_status;
SELECT COUNT(*) AS preserved_user_count FROM users;
SELECT COUNT(*) AS role_count FROM roles;
SELECT COUNT(*) AS permission_count FROM permissions;

SELECT table_name
FROM information_schema.tables
WHERE table_schema = DATABASE()
  AND table_name IN (
    'agents','gifts','gift_claims','pppm_items','catalog_products',
    'commerce_orders','commerce_order_items','wallets','ledger_entries',
    'entitlements','microgift_templates','microgift_instances',
    'microgift_claims','microgift_redemptions','microgift_review_items'
  )
ORDER BY table_name;

INSERT INTO schema_migrations (migration_key, description, checksum, applied_at)
VALUES (
  'stage_9e4_consolidated_stage1_to_stage9_upgrade',
  'Consolidated additive upgrade from the reviewed early Stage 1 install through Stage 9.',
  NULL,
  NOW()
)
ON DUPLICATE KEY UPDATE description = VALUES(description);
SQL;

$manifest = "-- GENERATED: {$generatedAt}\n-- SOURCE FILE COUNT: " . count($files) . "\n";
foreach ($sourceChecksums as $file => $checksum) {
    $manifest .= "-- {$file}: {$checksum}\n";
}

if (!is_dir($outputDir) && !mkdir($outputDir, 0775, true) && !is_dir($outputDir)) {
    throw new RuntimeException('Unable to create generated SQL directory.');
}

$output = $header . $manifest . implode('', $sections) . $verification . "\n";
if (file_put_contents($outputPath, $output) === false) {
    throw new RuntimeException('Unable to write consolidated SQL file.');
}

echo $outputPath . PHP_EOL;
echo 'Generated bytes: ' . strlen($output) . PHP_EOL;
echo 'Source files: ' . count($files) . PHP_EOL;
