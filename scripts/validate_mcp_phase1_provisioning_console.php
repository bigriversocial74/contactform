<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found.');
}

$root = dirname(__DIR__);
$failures = [];
$checks = [];
$check = static function (bool $condition, string $name, string $detail = '') use (&$failures, &$checks): void {
    $checks[$name] = $condition;
    if (!$condition) {
        $failures[] = $detail !== '' ? $name . ': ' . $detail : $name;
    }
};
$source = static function (string $path) use ($root): string {
    $value = file_get_contents($root . '/' . $path);
    return is_string($value) ? $value : '';
};

$releasePath = $root . '/config/mcp_phase1_provisioning_console_release.php';
$check(is_file($releasePath), 'release_manifest_exists');
$release = is_file($releasePath) ? require $releasePath : [];
$check(($release['release_key'] ?? null) === 'microgifter_mcp_phase1_provisioning_console_v1', 'release_key_locked');
$check(($release['required_migrations'] ?? null) === [], 'release_adds_no_sql');
$check(($release['foundation_migration'] ?? null) === '20260720_microgifter_mcp_automation_foundation_v1.sql', 'foundation_migration_locked');
$check(($release['permission'] ?? null) === 'admin.settings.manage', 'permission_locked');

$files = [
    'admin/mcp-connections.php',
    'api/admin/_mcp_connections.php',
    'api/admin/mcp-connections.php',
    'api/admin/mcp-connection-create.php',
    'api/admin/mcp-connection-action.php',
    'api/admin/mcp-runtime-credentials.php',
    'assets/js/admin-mcp-connections.js',
    'assets/css/admin-mcp-connections.css',
    'docs/MICROGIFTER_MCP_PHASE1_PROVISIONING_CONSOLE_RUNBOOK.md',
    'tests/phpunit/McpPhase1ProvisioningConsoleV1ContractTest.php',
];
foreach ($files as $path) {
    $check(is_file($root . '/' . $path), 'file_' . str_replace(['/', '.', '-'], '_', $path));
}

$helper = $source('api/admin/_mcp_connections.php');
$read = $source('api/admin/mcp-connections.php');
$create = $source('api/admin/mcp-connection-create.php');
$action = $source('api/admin/mcp-connection-action.php');
$credentials = $source('api/admin/mcp-runtime-credentials.php');
$page = $source('admin/mcp-connections.php');
$javascript = $source('assets/js/admin-mcp-connections.js');
$permissions = $source('includes/admin-permission-matrix.php');
$sidebar = $source('includes/admin-sidebar.php');

$check(str_contains($helper, "mg_require_permission('admin.settings.manage')"), 'admin_permission_required');
$check(str_contains($create, 'mg_require_csrf_for_write($input)'), 'create_csrf_required');
$check(str_contains($action, 'mg_require_csrf_for_write($input)'), 'action_csrf_required');
$check(str_contains($credentials, 'mg_require_csrf_for_write($input)'), 'credential_csrf_required');
$check(str_contains($read, "mg_rate_limit('admin.mcp_connections.read'"), 'read_rate_limit');
$check(str_contains($create, "mg_rate_limit('admin.mcp_connection.create'"), 'create_rate_limit');
$check(str_contains($credentials, "mg_rate_limit('admin.mcp_runtime_credentials.generate'"), 'credential_rate_limit');
$check(str_contains($helper, "maximum_operation_class,metadata_json"), 'client_read_only_insert');
$check(str_contains($helper, "'active','read',1,NOW()"), 'connection_read_only_insert');
$check(str_contains($helper, "active=1 AND grantable=1 AND operation_class='read'"), 'scope_catalog_enforced');
$check(str_contains($helper, 'merchant_team_members'), 'workspace_membership_verified');
$check(str_contains($helper, "mg_admin_mcp_text($input['reason']"), 'action_reason_required');
$check(str_contains($helper, "mg_audit('admin_mcp_connection_provision'"), 'provision_audited');
$check(str_contains($helper, "mg_security_log('medium', 'admin.mcp_runtime_credentials.generated'"), 'credential_security_event');
$check(str_contains($helper, 'random_bytes(32)'), 'bearer_cryptographic_randomness');
$check(str_contains($helper, "hash('sha256', $bearerToken)"), 'bearer_hash_generated');
$check(str_contains($helper, 'random_bytes(48)'), 'bridge_secret_cryptographic_randomness');
$check(str_contains($helper, "'secrets_persisted' => false"), 'secret_non_persistence_declared');
$check(!preg_match('/(?:INSERT|UPDATE)[^;]{0,400}(?:bearer_token|bridge_secret)/is', $helper), 'secrets_not_persisted');
$check(str_contains($credentials, "header('Cache-Control: private, no-store, max-age=0')"), 'credential_response_no_store');
$check(str_contains($page, "mg_require_admin_page_key('admin.mcp_connections')"), 'page_permission_gate');
$check(str_contains($permissions, "'admin.mcp_connections'"), 'permission_page_registered');
$check(str_contains($sidebar, "'mcp-connections'"), 'sidebar_registered');
$check(str_contains($javascript, '/api/admin/mcp-connection-create.php'), 'ui_provision_api_wired');
$check(str_contains($javascript, '/api/admin/mcp-runtime-credentials.php'), 'ui_credentials_api_wired');
$check(!str_contains($javascript, 'localStorage'), 'secrets_not_local_storage');
$check(!str_contains($javascript, 'sessionStorage'), 'secrets_not_session_storage');

$score = (int)round((count(array_filter($checks)) / max(1, count($checks))) * 10, 1);
if ($failures !== []) {
    fwrite(STDERR, "MCP Phase 1 provisioning console validation failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo json_encode([
    'ok' => true,
    'release' => $release['release_key'] ?? null,
    'checks' => count($checks),
    'score' => $score . '/10',
    'sql_required' => false,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
