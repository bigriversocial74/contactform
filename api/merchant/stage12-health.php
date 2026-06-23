<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';

mg_require_method('GET');
$user = mg_require_permission('merchant.campaigns.view');
$merchantId = (int) $user['id'];
$pdo = mg_db();
mg_merchant_ensure_workspace($pdo, $user);

$requiredTables = [
    'reward_templates',
    'campaigns',
    'campaign_contacts',
    'campaign_events',
    'wallet_items',
];
$requiredFiles = [
    'api/public/offers/search.php',
    'api/public/offers/detail.php',
    'api/public/offers/recommendations.php',
    'api/public/offers/feedback.php',
    'api/public/wallet/add.php',
    'api/account/wallet-items.php',
    'api/account/wallet-claim.php',
    'api/merchant/campaign-detail.php',
    'api/merchant/campaign-insights.php',
    'api/merchant/campaign-next-step.php',
    'api/merchant/wallet-redeem.php',
    'campaign.php',
    'wallet.php',
    'offers.php',
];

$root = dirname(__DIR__, 2);
$fileResults = [];
foreach ($requiredFiles as $file) {
    $fileResults[$file] = is_file($root . '/' . $file);
}

$tableResults = [];
try {
    foreach ($requiredTables as $table) {
        $stmt = $pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1');
        $stmt->execute([$table]);
        $tableResults[$table] = (bool) $stmt->fetchColumn();
    }

    $counts = [];
    foreach (['campaigns','reward_templates','wallet_items','campaign_events'] as $table) {
        $stmt = $pdo->query('SELECT COUNT(*) FROM ' . $table);
        $counts[$table] = (int) $stmt->fetchColumn();
    }

    $ok = !in_array(false, $fileResults, true) && !in_array(false, $tableResults, true);
    mg_ok([
        'stage' => '12H',
        'ready' => $ok,
        'merchant_id' => $merchantId,
        'files' => $fileResults,
        'tables' => $tableResults,
        'counts' => $counts,
    ]);
} catch (Throwable $error) {
    mg_security_log('warning', 'merchant.stage12_health.unavailable', 'Stage 12 health check unavailable.', ['exception_class' => $error::class], $merchantId);
    mg_ok([
        'stage' => '12H',
        'ready' => false,
        'merchant_id' => $merchantId,
        'files' => $fileResults,
        'tables' => $tableResults,
        'counts' => [],
        'error' => 'Stage 12 schema health could not be verified.',
    ], 'Stage 12 health unavailable until the schema is installed.');
}
