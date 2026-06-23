<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$requiredFiles = [
    'api/merchant/stage12-health.php',
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
    'assets/js/stage12-agent-offers.js',
    'assets/js/stage12-agent-action-center.js',
    'assets/js/stage12-wallet.js',
    'assets/js/stage12-campaign-tools.js',
    'campaign.php',
    'wallet.php',
    'offers.php',
];
$read = static function(string $file) use ($root): string {
    return is_file($root . '/' . $file) ? (string) file_get_contents($root . '/' . $file) : '';
};
$exists = [];
$ok = true;
foreach ($requiredFiles as $file) {
    $exists[$file] = is_file($root . '/' . $file);
    $ok = $ok && $exists[$file];
}
$checks = [
    'health_endpoint_checks_files' => str_contains($read('api/merchant/stage12-health.php'), 'requiredFiles') && str_contains($read('api/merchant/stage12-health.php'), 'ready'),
    'health_endpoint_checks_tables' => str_contains($read('api/merchant/stage12-health.php'), 'information_schema.tables') && str_contains($read('api/merchant/stage12-health.php'), 'wallet_items'),
    'public_offer_loop' => str_contains($read('api/public/offers/search.php'), 'agent_discoverable') && str_contains($read('api/public/offers/detail.php'), 'related_offers') && str_contains($read('api/public/offers/recommendations.php'), 'recommendation_score'),
    'feedback_loop' => str_contains($read('api/public/offers/feedback.php'), 'agent_offer.feedback') && str_contains($read('assets/js/stage12-agent-offers.js'), '/api/public/offers/feedback.php'),
    'wallet_loop' => str_contains($read('api/public/wallet/add.php'), 'approved_by_user') && str_contains($read('api/account/wallet-claim.php'), 'wallet_item.claimed') && str_contains($read('api/merchant/wallet-redeem.php'), 'wallet_item.redeemed'),
    'campaign_ops_loop' => str_contains($read('api/merchant/campaign-detail.php'), 'campaign_events') && str_contains($read('api/merchant/campaign-insights.php'), 'projected_30d') && str_contains($read('api/merchant/campaign-next-step.php'), 'merchant.next_step'),
    'ui_loop' => str_contains($read('offers.php'), 'data-stage12-agent-offers') && str_contains($read('wallet.php'), '/account.php') && str_contains($read('campaign.php'), 'data-public-campaign'),
    'action_center_logging' => str_contains($read('assets/js/stage12-agent-action-center.js'), '/api/merchant/campaign-next-step.php') && str_contains($read('assets/js/stage12-agent-action-center.js'), 'data-next-step'),
];
foreach ($checks as $pass) { $ok = $ok && $pass; }

$syntaxFiles = array_values(array_filter($requiredFiles, static fn(string $file): bool => str_ends_with($file, '.php')));
$syntax = [];
foreach ($syntaxFiles as $file) {
    $cmd = 'php -l ' . escapeshellarg($root . '/' . $file) . ' 2>&1';
    exec($cmd, $output, $code);
    $syntax[$file] = ['ok' => $code === 0, 'output' => implode("\n", $output)];
    $ok = $ok && $code === 0;
}

echo json_encode(['ok' => $ok, 'exists' => $exists, 'checks' => $checks, 'syntax' => $syntax], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit($ok ? 0 : 1);
