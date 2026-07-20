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

$releasePath = $root . '/config/mcp_phase1_foundation_release.php';
$check(is_file($releasePath), 'release_manifest_exists');
$release = is_file($releasePath) ? require $releasePath : [];
$check(is_array($release), 'release_manifest_is_array');
$check(($release['release_key'] ?? null) === 'microgifter_mcp_phase1_foundation_v1', 'release_key_locked');
$check(in_array('microgifter_mcp_phase0_contract_v1', (array)($release['depends_on'] ?? []), true), 'phase0_dependency_locked');
$check(in_array('task_agent_phase4_v1', (array)($release['depends_on'] ?? []), true), 'phase4_dependency_locked');

$migration = '20260720_microgifter_mcp_automation_foundation_v1.sql';
$check(($release['required_migrations'] ?? []) === [$migration], 'single_foundation_migration_locked');
$migrationPath = $root . '/database/' . $migration;
$check(is_file($migrationPath), 'foundation_migration_exists');
$sql = is_file($migrationPath) ? (string)file_get_contents($migrationPath) : '';

$tables = (array)($release['control_plane_tables'] ?? []);
$check(count($tables) === 16, 'control_plane_table_count');
foreach ($tables as $table) {
    $check(str_contains($sql, 'CREATE TABLE IF NOT EXISTS ' . $table), 'migration_creates_' . $table);
}
$check(str_contains($sql, "'20260720_microgifter_mcp_automation_foundation_v1'"), 'migration_marker_exists');
$check(!preg_match('/(?:password|client_secret|access_token|refresh_token)\s+(?:VARCHAR|TEXT|JSON)/i', $sql), 'migration_stores_no_live_secret_columns');

$migrations = require $root . '/config/migrations.php';
$ordered = array_values((array)($migrations['ordered_files'] ?? []));
$phase4 = array_search('20260720_task_agent_phase4_v1.sql', $ordered, true);
$foundation = array_search($migration, $ordered, true);
$check(is_int($phase4), 'phase4_migration_registered');
$check(is_int($foundation), 'foundation_migration_registered');
if (is_int($phase4) && is_int($foundation)) {
    $check($foundation === $phase4 + 1, 'foundation_migration_immediately_after_phase4');
}

$runtime = (array)($release['runtime'] ?? []);
foreach ([
    'enabled_by_default',
    'public_http_enabled',
    'oauth_enabled',
    'scheduler_enabled',
    'worker_enabled',
    'write_tools_enabled',
    'bounded_automation_enabled',
] as $flag) {
    $check(($runtime[$flag] ?? true) === false, 'runtime_flag_disabled_' . $flag);
}
$check(($runtime['language'] ?? null) === 'typescript', 'typescript_runtime_locked');
$check(($runtime['node'] ?? null) === '>=20', 'node_runtime_locked');

$serviceFiles = [
    'services/mcp/package.json',
    'services/mcp/package-lock.json',
    'services/mcp/tsconfig.json',
    'services/mcp/src/config.ts',
    'services/mcp/src/contracts.ts',
    'services/mcp/src/stateMachines.ts',
    'services/mcp/tests/foundation.test.mjs',
];
foreach ($serviceFiles as $path) {
    $check(is_file($root . '/' . $path), 'service_file_exists_' . str_replace(['/', '.'], '_', $path));
}

$configSource = is_file($root . '/services/mcp/src/config.ts')
    ? (string)file_get_contents($root . '/services/mcp/src/config.ts')
    : '';
$check(str_contains($configSource, 'DISABLED_FOUNDATION_CONFIG'), 'disabled_config_constant_exists');
$check(str_contains($configSource, 'boundedAutomationEnabled: false'), 'bounded_automation_disabled_in_code');
$check(str_contains($configSource, 'writeToolsEnabled: false'), 'write_tools_disabled_in_code');

$portSource = (string)file_get_contents($root . '/services/mcp/src/contracts.ts');
foreach ((array)($release['ports'] ?? []) as $port) {
    $check(str_contains($portSource, 'interface ' . $port), 'port_contract_exists_' . $port);
}

$package = json_decode((string)file_get_contents($root . '/services/mcp/package.json'), true);
$lock = json_decode((string)file_get_contents($root . '/services/mcp/package-lock.json'), true);
$check(is_array($package), 'package_json_valid');
$check(is_array($lock), 'package_lock_valid');
$check(($package['engines']['node'] ?? null) === '>=20', 'package_node_engine_locked');
$check(($package['devDependencies']['typescript'] ?? null) === '5.8.3', 'typescript_version_pinned');
$check(($lock['packages']['node_modules/typescript']['version'] ?? null) === '5.8.3', 'lockfile_typescript_version_pinned');

$boundaries = (array)($release['boundaries'] ?? []);
foreach ([
    'foundation_disabled_by_default',
    'no_public_http_endpoint',
    'no_external_oauth',
    'no_active_scheduler_or_worker',
    'no_write_tool_execution',
    'no_bounded_automation_execution',
    'no_node_database_credentials',
    'canonical_php_bridge_required',
    'durable_grant_required_for_unattended_execution',
    'existing_approval_center_remains_authoritative',
    'no_generic_sql_file_shell_callback_webhook_or_url_tools',
    'no_unbounded_autonomous_mode',
] as $boundary) {
    $check(in_array($boundary, $boundaries, true), 'boundary_locked_' . $boundary);
}

$result = [
    'suite' => 'microgifter_mcp_phase1_foundation_v1',
    'score' => $failures === [] ? '10.0/10' : 'failed',
    'checks' => $checks,
    'failures' => $failures,
];

if ($failures !== []) {
    fwrite(STDERR, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    exit(1);
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
