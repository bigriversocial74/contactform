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
    'examples/local-quest-rewards/wallet-actions.php',
    'examples/local-quest-rewards/admin.php',
    'examples/local-quest-rewards/admin-portal.php',
    'examples/local-quest-rewards/admin-quest-controls.php',
    'examples/local-quest-rewards/quest-controls.php',
    'examples/local-quest-rewards/assets/portal.css',
    'examples/local-quest-rewards/assets/portal.js',
    'examples/local-quest-rewards/quests.php',
    'examples/local-quest-rewards/webhook.php',
    'examples/local-quest-rewards/database/local_quest_rewards.sql',
    'examples/local-quest-rewards/data/README.md',
    'docs/microgift-permission-system-plan.md',
    'docs/public-api-third-party-wallet-claim.md',
    'docs/local-quest-app-assessment.md',
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
$walletActions = is_file($root . '/examples/local-quest-rewards/wallet-actions.php') ? (string)file_get_contents($root . '/examples/local-quest-rewards/wallet-actions.php') : '';
$admin = is_file($root . '/examples/local-quest-rewards/admin.php') ? (string)file_get_contents($root . '/examples/local-quest-rewards/admin.php') : '';
$adminPortal = is_file($root . '/examples/local-quest-rewards/admin-portal.php') ? (string)file_get_contents($root . '/examples/local-quest-rewards/admin-portal.php') : '';
$questControls = is_file($root . '/examples/local-quest-rewards/quest-controls.php') ? (string)file_get_contents($root . '/examples/local-quest-rewards/quest-controls.php') : '';
$questControlPage = is_file($root . '/examples/local-quest-rewards/admin-quest-controls.php') ? (string)file_get_contents($root . '/examples/local-quest-rewards/admin-quest-controls.php') : '';
$css = is_file($root . '/examples/local-quest-rewards/assets/portal.css') ? (string)file_get_contents($root . '/examples/local-quest-rewards/assets/portal.css') : '';
$js = is_file($root . '/examples/local-quest-rewards/assets/portal.js') ? (string)file_get_contents($root . '/examples/local-quest-rewards/assets/portal.js') : '';
$sql = is_file($root . '/examples/local-quest-rewards/database/local_quest_rewards.sql') ? (string)file_get_contents($root . '/examples/local-quest-rewards/database/local_quest_rewards.sql') : '';
$assessment = is_file($root . '/docs/local-quest-app-assessment.md') ? (string)file_get_contents($root . '/docs/local-quest-app-assessment.md') : '';
$requiresLogin = str_contains($index, 'header(\'Location: cover.php\')') || str_contains($index, 'header("Location: cover.php")');
$usesRealLink = str_contains($index, 'start_account_link');
$hasWallet = str_contains($wallet, 'claim_reward') && str_contains($app, 'lqr_wallet_rewards');
$claimReportsToApi = str_contains($wallet, 'lqr_action_claim_reward_reported') && str_contains($walletActions, '/api/public/v1/rewards/claim.php');
$hasAdmin = str_contains($admin, 'Quest app control center') && str_contains($admin, 'save_quest') && str_contains($admin, 'mark_claim_reported');
$hasStyledPortal = str_contains($adminPortal, 'Developer Portal') && str_contains($css, '.lq-sidebar:hover') && str_contains($css, '--lq-rail-open');
$hasQrGeo = str_contains($js, 'BarcodeDetector') && str_contains($js, 'navigator.geolocation') && str_contains($walletActions, 'claim_geolocation') && str_contains($index, 'qr_payload');
$hasSql = str_contains($sql, 'CREATE TABLE IF NOT EXISTS lqr_admin_users') && str_contains($sql, 'CREATE TABLE IF NOT EXISTS lqr_reward_claims') && str_contains($sql, 'max_total_rewards');
$hasQuestControls = str_contains($questControls, 'lqr_quest_availability') && str_contains($questControlPage, 'max_total_rewards') && str_contains($index, 'lqr_visible_quests');
$hasAssessment = str_contains($assessment, 'Overall: 6.8 / 10') && str_contains($assessment, 'What the app needs next');
$ok = $ok && $requiresLogin && $usesRealLink && $hasWallet && $claimReportsToApi && $hasAdmin && $hasStyledPortal && $hasQrGeo && $hasSql && $hasQuestControls && $hasAssessment;

echo json_encode(['ok' => $ok, 'files' => $rows, 'requires_login' => $requiresLogin, 'uses_real_account_linking' => $usesRealLink, 'has_wallet_claim_flow' => $hasWallet, 'claim_reports_to_microgifter_api' => $claimReportsToApi, 'has_admin_backend' => $hasAdmin, 'has_styled_portal' => $hasStyledPortal, 'has_qr_and_geolocation' => $hasQrGeo, 'has_sql_schema' => $hasSql, 'has_quest_controls' => $hasQuestControls, 'has_assessment' => $hasAssessment], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit($ok ? 0 : 1);
