<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$sqlFile = $root . '/database/stage_12j_campaign_feature_sql_hardening.sql';
$sql = is_file($sqlFile) ? (string) file_get_contents($sqlFile) : '';
$checks = [
    'sql_file_exists' => is_file($sqlFile),
    'campaign_events_campaign_nullable' => str_contains($sql, 'MODIFY campaign_id BIGINT UNSIGNED NULL'),
    'idempotent_index_helper' => str_contains($sql, 'mg_add_index_if_missing') && str_contains($sql, 'information_schema.statistics'),
    'campaign_event_indexes' => str_contains($sql, 'idx_campaign_events_merchant_type_created') && str_contains($sql, 'idx_campaign_events_wallet_created'),
    'wallet_indexes' => str_contains($sql, 'idx_wallet_items_merchant_status_updated') && str_contains($sql, 'idx_wallet_items_source_id'),
    'reward_template_indexes' => str_contains($sql, 'idx_reward_templates_agent_wallet') && str_contains($sql, 'idx_reward_templates_merchant_agent'),
    'campaign_indexes' => str_contains($sql, 'idx_campaigns_public_active') && str_contains($sql, 'idx_campaigns_slug_active'),
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
