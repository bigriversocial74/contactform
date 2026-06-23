<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$required = [
    'examples/local-quest-rewards/README.md',
    'examples/local-quest-rewards/app.php',
    'examples/local-quest-rewards/config.example.php',
    'examples/local-quest-rewards/cover.php',
    'examples/local-quest-rewards/signin.php',
    'examples/local-quest-rewards/index.php',
    'examples/local-quest-rewards/link-callback.php',
    'examples/local-quest-rewards/wallet.php',
    'examples/local-quest-rewards/quests.php',
    'examples/local-quest-rewards/webhook.php',
    'examples/local-quest-rewards/data/README.md',
    'docs/microgift-permission-system-plan.md',
    'docs/public-api-third-party-wallet-claim.md',
];

$ok = true;
$rows = [];
foreach ($required as $path) {
    $exists = is_file($root . '/' . $path);
    $ok = $ok && $exists;
    $rows[] = ['path' => $path, 'exists' => $exists];
}

$index = is_file($root . '/examples/local-quest-rewards/index.php') ? (string)file_get_contents($root . '/examples/local-quest-rewards/index.php') : '';
$app = is_file($root . '/examples/local-quest-rewards/app.php') ? (string)file_get_contents($root . '/examples/local-quest-rewards/app.php') : '';
$wallet = is_file($root . '/examples/local-quest-rewards/wallet.php') ? (string)file_get_contents($root . '/examples/local-quest-rewards/wallet.php') : '';
$requiresLogin = str_contains($index, 'header(\'Location: cover.php\')') || str_contains($index, 'header("Location: cover.php")');
$usesRealLink = str_contains($index, 'start_account_link');
$hasWallet = str_contains($wallet, 'claim_reward') && str_contains($app, 'lqr_wallet_rewards');
$claimReportsPendingApi = str_contains($app, 'pending_microgifter_claim_api') && str_contains($app, '/api/public/v1/rewards/claim.php');
$ok = $ok && $requiresLogin && $usesRealLink && $hasWallet && $claimReportsPendingApi;

echo json_encode(['ok' => $ok, 'files' => $rows, 'requires_login' => $requiresLogin, 'uses_real_account_linking' => $usesRealLink, 'has_wallet_claim_flow' => $hasWallet, 'claim_reporting_contract_needed' => $claimReportsPendingApi], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit($ok ? 0 : 1);
