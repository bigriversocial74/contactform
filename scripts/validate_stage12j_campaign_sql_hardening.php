<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$hardeningFile = $root . '/database/stage_12j_campaign_feature_sql_hardening.sql';
$fullImportFile = $root . '/database/stage_12_campaign_features_full_import.sql';
$hardeningSql = is_file($hardeningFile) ? (string) file_get_contents($hardeningFile) : '';
$fullSql = is_file($fullImportFile) ? (string) file_get_contents($fullImportFile) : '';
$checks = [
    'hardening_sql_file_exists' => is_file($hardeningFile),
    'full_import_sql_file_exists' => is_file($fullImportFile),
    'full_import_creates_reward_templates' => str_contains($fullSql, 'CREATE TABLE IF NOT EXISTS reward_templates'),
    'full_import_creates_campaigns' => str_contains($fullSql, 'CREATE TABLE IF NOT EXISTS campaigns'),
    'full_import_creates_campaign_contacts' => str_contains($fullSql, 'CREATE TABLE IF NOT EXISTS campaign_contacts'),
    'full_import_creates_wallet_items' => str_contains($fullSql, 'CREATE TABLE IF NOT EXISTS wallet_items'),
    'full_import_creates_campaign_events' => str_contains($fullSql, 'CREATE TABLE IF NOT EXISTS campaign_events'),
    'campaign_events_campaign_nullable' => str_contains($hardeningSql, 'MODIFY campaign_id BIGINT UNSIGNED NULL') && str_contains($fullSql, 'campaign_id BIGINT UNSIGNED NULL'),
    'idempotent_index_helper' => str_contains($hardeningSql, 'mg_add_index_if_missing') && str_contains($fullSql, 'mg_add_index_if_missing') && str_contains($fullSql, 'information_schema.statistics'),
    'campaign_event_indexes' => str_contains($fullSql, 'idx_campaign_events_merchant_type_created') && str_contains($fullSql, 'idx_campaign_events_wallet_created'),
    'wallet_indexes' => str_contains($fullSql, 'idx_wallet_items_merchant_status_updated') && str_contains($fullSql, 'idx_wallet_items_source_id'),
    'reward_template_indexes' => str_contains($fullSql, 'idx_reward_templates_agent_wallet') && str_contains($fullSql, 'idx_reward_templates_merchant_agent'),
    'campaign_indexes' => str_contains($fullSql, 'idx_campaigns_public_active') && str_contains($fullSql, 'idx_campaigns_slug_active'),
    'permissions_in_full_import' => str_contains($fullSql, 'merchant.reward_templates.view') && str_contains($fullSql, 'merchant.campaigns.manage'),
];
$codeFiles = [
    'api/public/wallet/add.php',
    'api/public/offers/feedback.php',
    'api/merchant/campaign-next-step.php',
];
foreach ($codeFiles as $file) {
    $content = is_file($root . '/' . $file) ? (string) file_get_contents($root . '/' . $file) : '';
    $checks['code_uses_nullable_campaign_events_' . basename($file, '.php')] = str_contains($content, 'INSERT INTO campaign_events') && str_contains($content, ', null,');
}
$ok = !in_array(false, $checks, true);
echo json_encode(['ok' => $ok, 'checks' => $checks], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit($ok ? 0 : 1);
