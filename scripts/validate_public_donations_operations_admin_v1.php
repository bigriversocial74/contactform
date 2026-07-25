<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit('Not found.'); }
$root = dirname(__DIR__);
$read = static function (string $path) use ($root): string {
    $content = file_get_contents($root . '/' . $path);
    if (!is_string($content) || trim($content) === '') throw new RuntimeException('Missing required file: ' . $path);
    return $content;
};
$must = static function (string $content, array $needles, string $label): void {
    foreach ($needles as $needle) {
        if (!str_contains($content, $needle)) throw new RuntimeException($label . ' missing contract: ' . $needle);
    }
};

$page = $read('admin/public-donations-operations.php');
$service = $read('api/admin/_public_donations_operations.php');
$readApi = $read('api/admin/public-donations-operations.php');
$actionApi = $read('api/admin/public-donations-operations-action.php');
$feature = $read('includes/public-donations-feature.php');
$sql = $read('database/20260724_public_donations_operations_admin_v1_single_install.sql');
$loader = $read('assets/js/admin-public-donations-operations.js');
$app = $read('assets/js/admin-public-donations-operations-app.js');
$ui = $read('assets/js/admin-public-donations-operations-ui.js');
$nav = $read('assets/js/admin-public-donations-nav.js');
$dashboard = $read('account-admin.php');

$must($page, [
    "mg_require_admin_page_key('admin.settings')",
    'data-public-donations-operations',
    'data-pdo-readiness',
    'data-pdo-rollout-form',
    'data-pdo-reconcile-form',
    'data-pdo-receipts',
    'data-pdo-operations',
    'UPDATE PUBLIC DONATIONS ROLLOUT',
    'RETURN TO ENVIRONMENT CONFIG',
    'REPAIR PUBLIC DONATIONS',
], 'admin page');

$must($service, [
    "mg_require_permission('admin.settings.manage')",
    'mg_admin_public_donations_actor_can_repair',
    'public_donations_operations_settings',
    'public_donations_reconciliation_receipts',
    'mg_public_donations_reconcile_apply',
    'mg_public_donations_reconcile_schema_ready',
    "'missing_attribution'",
    "'missing_links'",
    "'ownership_mismatches'",
    'UPDATE PUBLIC DONATIONS ROLLOUT',
    'RETURN TO ENVIRONMENT CONFIG',
    'REPAIR PUBLIC DONATIONS',
    'mg_audit(',
    'mg_event(',
    'mg_security_log(',
    'beginTransaction()',
    'rollBack()',
], 'admin service');

$must($readApi, [
    "mg_require_method('GET')",
    "mg_rate_limit('admin.public_donations_operations.read'",
    'Cache-Control: private, no-store',
], 'read API');
$must($actionApi, [
    "mg_require_method('POST')",
    'mg_require_csrf_for_write($input)',
    "mg_rate_limit('admin.public_donations_operations.write'",
    'MgAdminPublicDonationsOperationsException',
    'mg_fail_unexpected(',
], 'action API');

$must($feature, [
    'mg_public_donations_environment_rollout',
    'mg_public_donations_rollout_config',
    "'source' => 'environment'",
    "'source' => 'database_override'",
    "if (!\$row || empty(\$row['override_active']))",
    'MG_PUBLIC_DONATIONS_FEATURE_STATE',
    'MG_PUBLIC_DONATIONS_MERCHANT_IDS',
], 'rollout helper');

$must($sql, [
    'public_donations_operations_settings',
    'override_active TINYINT(1) NOT NULL DEFAULT 0',
    'public_donations_reconciliation_receipts',
    'UNIQUE KEY uq_public_donations_reconciliation_receipt_id',
    'admin.public_donations_operations.view',
    'admin.public_donations_operations.manage',
    'admin.public_donations_operations.repair',
    'ON DUPLICATE KEY UPDATE id=VALUES(id)',
], 'single installer');

$must($loader, [
    'admin-public-donations-operations-app.js',
    'admin-public-donations-nav.js',
], 'loader');
$must($app, [
    '/api/admin/public-donations-operations.php',
    '/api/admin/public-donations-operations-action.php',
    "action:'update_rollout'",
    "action:'return_to_environment'",
    "action:'reconcile'",
    'REPAIR PUBLIC DONATIONS',
], 'controller');
$must($ui, [
    'renderReadiness',
    'renderRollout',
    'renderReceipts',
    'renderOperations',
    'renderResult',
], 'renderer');
$must($nav, [
    '/admin/public-donations-operations.php',
    'Public Donations operations',
], 'navigation');
$must($dashboard, [
    'admin.public_donations_operations.view',
    'admin-public-donations-nav.js',
], 'admin dashboard');

if (preg_match('/INSERT\s+INTO\s+campaign_donation_rewards/i', $service) === 1) {
    throw new RuntimeException('Admin operations service must never invent donation attribution.');
}
if (preg_match('/UPDATE\s+(?:wallet_items|pppm_items|microgift_instances)\s+SET\s+(?:user_id|owner_user_id|recipient_user_id)/i', $service) === 1) {
    throw new RuntimeException('Admin operations service must never assign ownership.');
}
if (str_contains($sql, "VALUES\n  (1,1,'")) {
    throw new RuntimeException('Database rollout override must not activate during import.');
}
if (!str_contains($service, "mg_admin_permission_user_has(\$actor, 'admin.admin_agent.execute')")) {
    throw new RuntimeException('Repair execution must preserve the explicit elevated-permission path.');
}

echo "Public Donations Operations Admin contracts valid: 12/12.\n";
