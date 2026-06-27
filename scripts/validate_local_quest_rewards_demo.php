<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$required = [
    'examples/local-quest-rewards/README.md',
    'examples/local-quest-rewards/.gitignore',
    'examples/local-quest-rewards/app.php',
    'examples/local-quest-rewards/install.php',
    'examples/local-quest-rewards/install-lock.php',
    'examples/local-quest-rewards/security.php',
    'examples/local-quest-rewards/storage-sql.php',
    'examples/local-quest-rewards/config.example.php',
    'examples/local-quest-rewards/cover.php',
    'examples/local-quest-rewards/signin.php',
    'examples/local-quest-rewards/index.php',
    'examples/local-quest-rewards/developer-starter.php',
    'examples/local-quest-rewards/link-callback.php',
    'examples/local-quest-rewards/wallet.php',
    'examples/local-quest-rewards/wallet-actions.php',
    'examples/local-quest-rewards/admin.php',
    'examples/local-quest-rewards/admin-portal.php',
    'examples/local-quest-rewards/admin-quest-controls.php',
    'examples/local-quest-rewards/admin-auth.php',
    'examples/local-quest-rewards/admin-credentials.php',
    'examples/local-quest-rewards/admin-roles.php',
    'examples/local-quest-rewards/quest-controls.php',
    'examples/local-quest-rewards/assets/portal.css',
    'examples/local-quest-rewards/assets/portal.js',
    'examples/local-quest-rewards/assets/form-review.js',
    'examples/local-quest-rewards/quests.php',
    'examples/local-quest-rewards/webhook.php',
    'examples/local-quest-rewards/webhook-reconcile.php',
    'examples/local-quest-rewards/database/local_quest_rewards.sql',
    'examples/local-quest-rewards/database/local_quest_admin_auth.sql',
    'docs/microgift-permission-system-plan.md',
    'docs/public-api-third-party-wallet-claim.md',
    'docs/local-quest-admin-roles.md',
    'docs/local-quest-app-assessment.md',
    'docs/local-quest-admin-auth.md',
    'docs/local-quest-installer-hardening.md',
    'docs/local-quest-security-hardening.md',
];

$forbidden = [
    'examples/local-quest-rewards/scripts/migrate-json-to-sql.php',
    'examples/local-quest-rewards/data/README.md',
];

function lqr_validate_read(string $root, string $path): string
{
    $full = $root . '/' . $path;
    return is_file($full) ? (string)file_get_contents($full) : '';
}

$ok = true;
$rows = [];
foreach ($required as $path) {
    $exists = is_file($root . '/' . $path);
    $ok = $ok && $exists;
    $rows[] = ['path' => $path, 'exists' => $exists];
}
foreach ($forbidden as $path) {
    $exists = is_file($root . '/' . $path);
    $ok = $ok && !$exists;
    $rows[] = ['path' => $path, 'forbidden_exists' => $exists];
}

$index = lqr_validate_read($root, 'examples/local-quest-rewards/index.php');
$app = lqr_validate_read($root, 'examples/local-quest-rewards/app.php');
$developerStarter = lqr_validate_read($root, 'examples/local-quest-rewards/developer-starter.php');
$install = lqr_validate_read($root, 'examples/local-quest-rewards/install.php');
$installLock = lqr_validate_read($root, 'examples/local-quest-rewards/install-lock.php');
$gitignore = lqr_validate_read($root, 'examples/local-quest-rewards/.gitignore');
$reviewJs = lqr_validate_read($root, 'examples/local-quest-rewards/assets/form-review.js');
$adminRoles = lqr_validate_read($root, 'examples/local-quest-rewards/admin-roles.php');
$security = lqr_validate_read($root, 'examples/local-quest-rewards/security.php');
$storage = lqr_validate_read($root, 'examples/local-quest-rewards/storage-sql.php');
$wallet = lqr_validate_read($root, 'examples/local-quest-rewards/wallet.php');
$walletActions = lqr_validate_read($root, 'examples/local-quest-rewards/wallet-actions.php');
$webhook = lqr_validate_read($root, 'examples/local-quest-rewards/webhook.php');
$webhookReconcile = lqr_validate_read($root, 'examples/local-quest-rewards/webhook-reconcile.php');
$admin = lqr_validate_read($root, 'examples/local-quest-rewards/admin.php');
$adminPortal = lqr_validate_read($root, 'examples/local-quest-rewards/admin-portal.php');
$adminAuth = lqr_validate_read($root, 'examples/local-quest-rewards/admin-auth.php');
$adminCredentials = lqr_validate_read($root, 'examples/local-quest-rewards/admin-credentials.php');
$questControls = lqr_validate_read($root, 'examples/local-quest-rewards/quest-controls.php');
$questControlPage = lqr_validate_read($root, 'examples/local-quest-rewards/admin-quest-controls.php');
$css = lqr_validate_read($root, 'examples/local-quest-rewards/assets/portal.css');
$js = lqr_validate_read($root, 'examples/local-quest-rewards/assets/portal.js');
$sql = lqr_validate_read($root, 'examples/local-quest-rewards/database/local_quest_rewards.sql');
$adminAuthSql = lqr_validate_read($root, 'examples/local-quest-rewards/database/local_quest_admin_auth.sql');
$assessment = lqr_validate_read($root, 'docs/local-quest-app-assessment.md');
$adminRolesDoc = lqr_validate_read($root, 'docs/local-quest-admin-roles.md');
$adminAuthDoc = lqr_validate_read($root, 'docs/local-quest-admin-auth.md');
$installerDoc = lqr_validate_read($root, 'docs/local-quest-installer-hardening.md');
$securityDoc = lqr_validate_read($root, 'docs/local-quest-security-hardening.md');

$requiresLogin = str_contains($index, "header('Location: cover.php')") || str_contains($index, 'header("Location: cover.php")');
$usesRealLink = str_contains($index, 'start_account_link');
$hasWallet = str_contains($wallet, 'claim_reward') && str_contains($app, 'lqr_wallet_rewards');
$claimReportsToApi = str_contains($wallet, 'lqr_action_claim_reward_reported') && str_contains($walletActions, '/api/public/v1/rewards/claim.php');
$hasAdmin = (str_contains($admin, 'Quest app control center') || str_contains($admin, 'Local Quest Control Center')) && str_contains($admin, 'save_quest') && str_contains($admin, 'mark_claim_reported');
$hasStyledPortal = str_contains($adminPortal, 'Developer Portal') && str_contains($css, '.lq-sidebar:hover') && str_contains($css, '--lq-rail-open');
$hasQrGeo = str_contains($js, 'BarcodeDetector') && str_contains($js, 'navigator.geolocation') && str_contains($walletActions, 'claim_geolocation') && str_contains($index, 'qr_payload');
$hasSql = str_contains($sql, 'CREATE TABLE IF NOT EXISTS lqr_admin_users') && str_contains($sql, 'CREATE TABLE IF NOT EXISTS lqr_reward_claims') && str_contains($sql, 'max_total_rewards');
$hasQuestControls = str_contains($questControls, 'lqr_quest_availability') && str_contains($questControlPage, 'max_total_rewards') && str_contains($index, 'lqr_visible_quests');
$hasAdminAuth = str_contains($adminAuth, 'lqr_admin_create_user') && str_contains($adminAuth, 'lqr_admin_create_reset_token') && str_contains($adminCredentials, 'create_recovery') && str_contains($adminAuthSql, 'lqr_admin_password_resets');
$hasAdminRoles = str_contains($adminRoles, 'lqr_admin_role_map') && str_contains($adminRoles, 'lqr_admin_require_role') && str_contains($adminRoles, 'sponsor_viewer') && str_contains($adminRolesDoc, 'Local Quest admin roles');
$hasSqlRuntime = str_contains($app, "require_once __DIR__ . '/storage-sql.php'") && str_contains($app, 'lqr_sql_load_state(lqr_config())') && str_contains($app, 'lqr_sql_save_state(lqr_config(), $state)') && str_contains($storage, 'lqr_sql_load_state') && str_contains($storage, 'lqr_sql_save_state');
$noJsonRuntime = !str_contains($app, 'state.json') && !str_contains($app, 'lqr_state_path') && !str_contains($app, 'file_put_contents(lqr_state_path');
$hasSecurity = str_contains($app, "require_once __DIR__ . '/security.php'") && str_contains($app, 'lqr_require_csrf') && str_contains($security, 'lqr_auto_csrf_output') && str_contains($security, 'lqr_signed_payload') && str_contains($security, 'lqr_mark_replay');
$hasWebhookCsrfBypass = str_contains($app, 'LQR_SKIP_CSRF') && str_contains($webhook, "define('LQR_SKIP_CSRF', true)") && str_contains($webhook, 'Signed webhook status');
$hasWebhookReconciliation = str_contains($webhook, 'lqr_reconcile_microgifter_webhook') && str_contains($webhookReconcile, 'reward.delivered') && str_contains($webhookReconcile, 'confirmed_by_microgifter_webhook');
$hasDeveloperStarterPortal = str_contains($developerStarter, 'Developer Starter Portal') && str_contains($developerStarter, 'lq-tab-setup') && str_contains($developerStarter, '/api/public/v1/rewards/issue.php') && str_contains($developerStarter, '/api/public/v1/rewards/claim.php') && str_contains($index, 'developer-starter.php') && str_contains($wallet, 'developer-starter.php');
$hasInstaller = str_contains($install, 'Local Quest Installer') && str_contains($install, 'lqi_pdo') && str_contains($install, 'lqi_write_config') && str_contains($install, 'lqi_seed_owner');
$hasInstallerLock = str_contains($installLock, 'lqi_guard_installer') && str_contains($installLock, '.installed.lock') && str_contains($installLock, '.install-unlock') && str_contains($gitignore, '.installed.lock') && str_contains($gitignore, 'config.php');
$hasInstallReview = str_contains($reviewJs, 'Review setup before install') && str_contains($reviewJs, 'Confirm and install') && str_contains($reviewJs, 'protected value');
$hasInstallerDoc = str_contains($installerDoc, 'Local Quest installer hardening') && str_contains($installerDoc, '.installed.lock') && str_contains($installerDoc, '.install-unlock');
$hasAssessment = str_contains($assessment, 'Overall: 7.5 / 10') && str_contains($assessment, 'SQL-only runtime stage completed');
$hasAdminAuthDoc = str_contains($adminAuthDoc, 'Local Quest admin access hardening') && str_contains($adminAuthDoc, 'one-time recovery tokens');
$hasSecurityDoc = str_contains($securityDoc, 'Local Quest security hardening') && str_contains($securityDoc, 'automatic hidden CSRF token injection');

$ok = $ok && $requiresLogin && $usesRealLink && $hasWallet && $claimReportsToApi && $hasAdmin && $hasStyledPortal && $hasQrGeo && $hasSql && $hasQuestControls && $hasAdminAuth && $hasAdminRoles && $hasSqlRuntime && $noJsonRuntime && $hasSecurity && $hasWebhookCsrfBypass && $hasWebhookReconciliation && $hasDeveloperStarterPortal && $hasInstaller && $hasInstallerLock && $hasInstallReview && $hasInstallerDoc && $hasAssessment && $hasAdminAuthDoc && $hasSecurityDoc;

echo json_encode([
    'ok' => $ok,
    'files' => $rows,
    'requires_login' => $requiresLogin,
    'uses_real_account_linking' => $usesRealLink,
    'has_wallet_claim_flow' => $hasWallet,
    'claim_reports_to_microgifter_api' => $claimReportsToApi,
    'has_admin_backend' => $hasAdmin,
    'has_styled_portal' => $hasStyledPortal,
    'has_qr_and_geolocation' => $hasQrGeo,
    'has_sql_schema' => $hasSql,
    'has_quest_controls' => $hasQuestControls,
    'has_admin_auth' => $hasAdminAuth,
    'has_admin_roles' => $hasAdminRoles,
    'has_sql_runtime' => $hasSqlRuntime,
    'no_json_runtime' => $noJsonRuntime,
    'has_security' => $hasSecurity,
    'has_webhook_csrf_bypass' => $hasWebhookCsrfBypass,
    'has_webhook_reconciliation' => $hasWebhookReconciliation,
    'has_developer_starter_portal' => $hasDeveloperStarterPortal,
    'has_installer' => $hasInstaller,
    'has_installer_lock' => $hasInstallerLock,
    'has_install_review' => $hasInstallReview,
    'has_installer_doc' => $hasInstallerDoc,
    'has_assessment' => $hasAssessment,
    'has_admin_auth_doc' => $hasAdminAuthDoc,
    'has_security_doc' => $hasSecurityDoc,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit($ok ? 0 : 1);
