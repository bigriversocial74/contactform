<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found.');
}

$root = dirname(__DIR__);
$read = static function (string $path) use ($root): string {
    $fullPath = $root . '/' . $path;
    $content = is_file($fullPath) ? file_get_contents($fullPath) : false;
    if (!is_string($content) || trim($content) === '') {
        throw new RuntimeException('Missing required file: ' . $path);
    }
    return $content;
};

$containsAll = static function (string $content, array $needles): bool {
    foreach ($needles as $needle) {
        if (!str_contains($content, $needle)) return false;
    }
    return true;
};

$matches = static function (string $content, string $pattern): bool {
    return preg_match($pattern, $content) === 1;
};

$files = [
    'page' => $read('admin/public-donations-operations.php'),
    'service' => $read('api/admin/_public_donations_operations.php'),
    'projection' => $read('api/admin/_public_donations_operations_projection.php'),
    'read_api' => $read('api/admin/public-donations-operations.php'),
    'action_api' => $read('api/admin/public-donations-operations-action.php'),
    'feature' => $read('includes/public-donations-feature.php'),
    'reconciliation' => $read('includes/public-donations-reconciliation.php'),
    'matrix' => $read('includes/admin-permission-matrix.php'),
    'sidebar' => $read('includes/admin-sidebar.php'),
    'sql' => $read('database/20260724_public_donations_operations_admin_v1_single_install.sql'),
    'loader' => $read('assets/js/admin-public-donations-operations.js'),
    'app' => $read('assets/js/admin-public-donations-operations-app.js'),
    'ui' => $read('assets/js/admin-public-donations-operations-ui.js'),
    'nav' => $read('assets/js/admin-public-donations-nav.js'),
    'dashboard' => $read('account-admin.php'),
];

$checks = [];
$checks['protected admin workspace'] = $containsAll($files['page'], [
    "mg_require_admin_page_key('admin.public_donations_operations')",
    'data-public-donations-operations',
    'data-pdo-readiness',
    'data-pdo-rollout-form',
    'data-pdo-reconcile-form',
    'data-pdo-receipts',
    'data-pdo-operations',
]);
$checks['typed operational confirmations'] = $containsAll($files['page'] . "\n" . $files['service'], [
    'UPDATE PUBLIC DONATIONS ROLLOUT',
    'RETURN TO ENVIRONMENT CONFIG',
    'REPAIR PUBLIC DONATIONS',
]);
$checks['operations service'] = $containsAll($files['service'], [
    'mg_admin_public_donations_actor_can_repair',
    'public_donations_operations_settings',
    'public_donations_reconciliation_receipts',
    'mg_public_donations_reconcile_apply',
    'mg_public_donations_reconcile_schema_ready',
    'mg_audit(',
    'mg_event(',
    'mg_security_log(',
    'beginTransaction()',
    'rollBack()',
]);
$checks['canonical reconciliation engine'] = $containsAll($files['reconciliation'], [
    "'missing_attribution'",
    "'missing_links'",
    "'ownership_mismatches'",
    'mg_public_donations_reconcile_detect',
    'mg_public_donations_reconcile_apply',
    "'repairable' => false",
]);
$checks['view and manage separation'] = $containsAll($files['projection'], [
    'mg_admin_public_donations_require_operations_user(bool $manage = false)',
    "'admin.public_donations_operations.manage'",
    "'admin.public_donations_operations.view'",
    "'access_mode' => \$manage ? 'manage' : 'view'",
    'admin.public_donations_operations.permission_denied',
    "'view' => true",
    "'manage' => \$canManage",
    "'repair' => \$canManage && mg_admin_public_donations_actor_can_repair(\$actor)",
]);
$checks['operations projections'] = $containsAll($files['projection'], [
    'mg_admin_public_donations_search_merchants_projection',
    'mg_admin_public_donations_summary_projection',
    'mg_admin_public_donations_recent_operations_projection',
    'mg_admin_public_donations_read_projection',
    "SUM(status='recalled') AS recalled",
    'max(0, $gross - $recalled)',
    'campaign.title AS campaign_name',
]);
$checks['read API security'] = $containsAll($files['read_api'], [
    "mg_require_method('GET')",
    'mg_admin_public_donations_require_operations_user()',
    "mg_rate_limit('admin.public_donations_operations.read'",
    'mg_admin_public_donations_read_projection',
    'Cache-Control: private, no-store',
]);
$checks['write API security'] = $containsAll($files['action_api'], [
    "mg_require_method('POST')",
    'mg_admin_public_donations_require_operations_user(true)',
    'mg_require_csrf_for_write($input)',
    "mg_rate_limit('admin.public_donations_operations.write'",
    'mg_admin_public_donations_read_projection',
    'MgAdminPublicDonationsOperationsException',
    'mg_fail_unexpected(',
]);
$checks['rollout precedence'] = $containsAll($files['feature'], [
    'mg_public_donations_environment_rollout',
    'mg_public_donations_rollout_config',
    "'source' => 'environment'",
    "'source' => 'database_override'",
    "empty(\$row['override_active'])",
    'MG_PUBLIC_DONATIONS_FEATURE_STATE',
    'MG_PUBLIC_DONATIONS_MERCHANT_IDS',
]);
$checks['permission matrix'] = $containsAll($files['matrix'], [
    "'admin.public_donations_operations'",
    "'admin.public_donations_operations.view'",
    "'admin.public_donations_operations.manage'",
    "'admin.public_donations_operations.repair' => ['admin.admin_agent.execute']",
]);
$checks['shared admin navigation'] = $containsAll($files['sidebar'], [
    "\$canPublicDonationsOperations = \$canAdminPage('admin.public_donations_operations')",
    "'public-donations-operations' => [",
    "'href' => '/admin/public-donations-operations.php'",
    "'visible' => \$canPublicDonationsOperations",
]);
$checks['single installer'] = $containsAll($files['sql'], [
    'public_donations_operations_settings',
    'override_active TINYINT(1) NOT NULL DEFAULT 0',
    'public_donations_reconciliation_receipts',
    'UNIQUE KEY uq_public_donations_reconciliation_receipt_id',
    'admin.public_donations_operations.view',
    'admin.public_donations_operations.manage',
    'admin.public_donations_operations.repair',
    'ON DUPLICATE KEY UPDATE id=VALUES(id)',
]);
$checks['frontend loader and controller'] = $containsAll($files['loader'] . "\n" . $files['app'], [
    'admin-public-donations-operations-app.js',
    'admin-public-donations-nav.js',
    '/api/admin/public-donations-operations.php',
    '/api/admin/public-donations-operations-action.php',
    "action:'update_rollout'",
    "action:'return_to_environment'",
    "action:'reconcile'",
    'Read-only access: rollout controls require the manage permission.',
    'Read-only access: reconciliation execution requires the manage permission.',
]);
$checks['frontend renderer'] = $containsAll($files['ui'], [
    'renderReadiness',
    'renderRollout',
    'renderReceipts',
    'renderOperations',
    'renderResult',
]);
$checks['dashboard shortcut'] = $containsAll($files['nav'] . "\n" . $files['dashboard'], [
    '/admin/public-donations-operations.php',
    'Public Donations operations',
    'admin.public_donations_operations.view',
    'admin-public-donations-nav.js',
]);

$boundary = $files['service'] . "\n" . $files['reconciliation'];
$forbiddenAttributionWrite = $matches($boundary, '/INSERT\s+INTO\s+campaign_donation_rewards/i');
$forbiddenOwnershipWrite = $matches(
    $boundary,
    '/UPDATE\s+(?:wallet_items|pppm_items|microgift_instances)\s+SET\s+(?:user_id|owner_user_id|recipient_user_id)\s*=/i'
);
$overrideActivatesOnImport = $matches(
    $files['sql'],
    '/INSERT\s+INTO\s+public_donations_operations_settings[\s\S]*?VALUES\s*\(\s*1\s*,\s*1\s*,/i'
);
$checks['safety boundaries'] = !$forbiddenAttributionWrite
    && !$forbiddenOwnershipWrite
    && !$overrideActivatesOnImport
    && str_contains($files['service'], "mg_admin_permission_user_has(\$actor, 'admin.admin_agent.execute')");

$failed = array_keys(array_filter($checks, static fn(bool $ok): bool => !$ok));
foreach ($checks as $name => $ok) {
    echo sprintf("[%s] %s\n", $ok ? 'PASS' : 'FAIL', $name);
}

if ($failed !== []) {
    fwrite(STDERR, 'Public Donations Operations Admin contract failures: ' . implode(', ', $failed) . PHP_EOL);
    exit(1);
}

echo 'Public Donations Operations Admin contracts valid: ' . count($checks) . '/' . count($checks) . ".\n";
