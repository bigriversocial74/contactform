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

$releasePath = $root . '/config/mcp_phase1_protocol_release.php';
$check(is_file($releasePath), 'release_manifest_exists');
$release = is_file($releasePath) ? require $releasePath : [];
$check(($release['release_key'] ?? null) === 'microgifter_mcp_phase1_protocol_v1', 'release_key_locked');
$check(in_array('microgifter_mcp_phase1_foundation_v1', (array)($release['depends_on'] ?? []), true), 'foundation_dependency_locked');
$check(($release['required_migrations'] ?? null) === [], 'protocol_requires_no_sql');
$check(($release['protocol']['revision'] ?? null) === '2025-11-25', 'stable_protocol_revision_locked');
$check(($release['protocol']['transport'] ?? null) === 'streamable_http', 'streamable_http_locked');
$check(($release['protocol']['mode'] ?? null) === 'stateless', 'stateless_mode_locked');
$check(($release['protocol']['sdk_version'] ?? null) === '1.29.0', 'stable_sdk_version_locked');

$package = json_decode($source('services/mcp/package.json'), true);
$lock = json_decode($source('services/mcp/package-lock.json'), true);
$check(is_array($package), 'package_json_valid');
$check(is_array($lock), 'package_lock_valid');
$check(($package['dependencies']['@modelcontextprotocol/sdk'] ?? null) === '1.29.0', 'sdk_exactly_pinned');
$check(($package['dependencies']['zod'] ?? null) === '4.4.3', 'zod_exactly_pinned');
$check(($package['devDependencies']['@types/node'] ?? null) === '20.19.43', 'node_types_exactly_pinned');
$check(($package['devDependencies']['@types/express'] ?? null) === '5.0.6', 'express_types_exactly_pinned');
$check(($lock['packages']['node_modules/@modelcontextprotocol/sdk']['version'] ?? null) === '1.29.0', 'sdk_lockfile_version');
$check(!str_contains($source('services/mcp/package-lock.json'), 'packages.applied-caas'), 'lockfile_has_no_internal_registry');

$requiredFiles = [
    'services/mcp/src/protocolConfig.ts',
    'services/mcp/src/auth/internalToken.ts',
    'services/mcp/src/http/origin.ts',
    'services/mcp/src/http/app.ts',
    'services/mcp/src/rateLimit.ts',
    'services/mcp/src/receipts.ts',
    'services/mcp/src/tools/registry.ts',
    'services/mcp/src/server.ts',
    'services/mcp/tests/protocol.test.mjs',
];
foreach ($requiredFiles as $path) {
    $check(is_file($root . '/' . $path), 'file_exists_' . str_replace(['/', '.'], '_', $path));
}

$auth = $source('services/mcp/src/auth/internalToken.ts');
$http = $source('services/mcp/src/http/app.ts');
$registry = $source('services/mcp/src/tools/registry.ts');
$config = $source('services/mcp/src/protocolConfig.ts');
$check(str_contains($auth, 'timingSafeEqual'), 'constant_time_token_comparison');
$check(str_contains($auth, 'createHash("sha256")'), 'sha256_token_hashing');
$check(str_contains($http, 'StreamableHTTPServerTransport'), 'official_streamable_http_transport');
$check(str_contains($http, 'sessionIdGenerator: undefined'), 'stateless_transport_configuration');
$check(str_contains($http, 'response.status(403)'), 'invalid_origin_returns_403');
$check(str_contains($http, 'response.status(401)'), 'invalid_token_returns_401');
$check(str_contains($http, 'response.status(429)'), 'rate_limit_returns_429');
$check(str_contains($config, 'maximumOperationClass: "read"'), 'internal_context_read_only');
$check(str_contains($registry, 'microgifter.account.get_connection_context'), 'account_context_tool_registered');
$check(str_contains($registry, 'MICROGIFTER_TOOL_DISABLED'), 'catalog_tools_fail_closed');
$check(!preg_match('/(?:mysql|PDO|SELECT\s+|INSERT\s+|UPDATE\s+|DELETE\s+FROM)/i', $registry), 'registry_contains_no_database_logic');

$toolNames = array_keys((array)($release['initial_tools'] ?? []));
$check($toolNames === [
    'microgifter.account.get_connection_context',
    'microgifter.catalog.search',
    'microgifter.catalog.get_item',
], 'initial_tool_order_locked');

foreach ((array)($release['runtime'] ?? []) as $key => $value) {
    if (str_ends_with((string)$key, '_enabled') || str_ends_with((string)$key, '_enabled_by_default')) {
        $check($value === false, 'runtime_disabled_' . $key);
    }
}

$result = [
    'suite' => 'microgifter_mcp_phase1_protocol_v1',
    'score' => $failures === [] ? '10.0/10' : 'failed',
    'checks' => $checks,
    'failures' => $failures,
];
if ($failures !== []) {
    fwrite(STDERR, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    exit(1);
}
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
