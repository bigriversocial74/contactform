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

$releasePath = $root . '/config/mcp_phase1_canonical_bridge_release.php';
$check(is_file($releasePath), 'release_manifest_exists');
$release = is_file($releasePath) ? require $releasePath : [];
$check(($release['release_key'] ?? null) === 'microgifter_mcp_phase1_canonical_bridge_v1', 'release_key_locked');
$check(($release['required_migrations'] ?? null) === [], 'bridge_adds_no_sql');
$check(($release['foundation_migration'] ?? null) === '20260720_microgifter_mcp_automation_foundation_v1.sql', 'foundation_migration_locked');
$check(($release['endpoint'] ?? null) === '/api/internal/mcp-bridge.php', 'endpoint_locked');

$helper = $source('api/internal/_mcp_bridge.php');
$endpoint = $source('api/internal/mcp-bridge.php');
$bridge = $source('services/mcp/src/bridge/canonicalBridge.ts');
$registry = $source('services/mcp/src/tools/registry.ts');
$config = $source('services/mcp/src/protocolConfig.ts');
$productDiscovery = $source('includes/profiles/_product_discovery.php');

foreach ([
    'api/internal/_mcp_bridge.php',
    'api/internal/mcp-bridge.php',
    'services/mcp/src/bridge/canonicalBridge.ts',
    'services/mcp/tests/bridge.test.mjs',
] as $path) {
    $check(is_file($root . '/' . $path), 'file_' . str_replace(['/', '.'], '_', $path));
}

$check(str_contains($helper, "hash_hmac('sha256'"), 'php_hmac_sha256');
$check(str_contains($helper, 'hash_equals($expected, $signature)'), 'php_constant_time_signature_check');
$check(str_contains($helper, 'abs(time() - (int)$timestamp) > 300'), 'timestamp_window_locked');
$check(str_contains($helper, "'bridge-nonce:' . $nonce"), 'nonce_replay_reservation');
$check(str_contains($helper, 'mcp_connection_scopes'), 'database_scope_resolution');
$check(str_contains($helper, 'merchant_team_members'), 'workspace_membership_recheck');
$check(str_contains($helper, 'mg_product_discovery_search'), 'canonical_catalog_search_reused');
$check(str_contains($helper, 'mg_public_product_load'), 'canonical_product_detail_reused');
$check(str_contains($helper, 'mcp_tool_invocations'), 'durable_receipt_table');
$check(!str_contains($helper, "'address_line1' =>"), 'exact_address_not_projected');
$check(str_contains($endpoint, "'connection.resolve'"), 'connection_resolve_dispatch');
$check(str_contains($endpoint, "'catalog.search'"), 'catalog_search_dispatch');
$check(str_contains($endpoint, "'catalog.get_item'"), 'catalog_item_dispatch');
$check(str_contains($endpoint, "'receipt.record'"), 'receipt_dispatch');

$check(str_contains($bridge, 'createHmac("sha256"'), 'node_hmac_sha256');
$check(str_contains($bridge, 'x-microgifter-mcp-signature'), 'node_signature_header');
$check(str_contains($bridge, 'AbortController'), 'bridge_timeout_control');
$check(str_contains($registry, 'dependencies.bridge.searchCatalog'), 'catalog_search_activated');
$check(str_contains($registry, 'dependencies.bridge.getCatalogItem'), 'catalog_item_activated');
$check(str_contains($registry, 'inputFingerprint'), 'receipt_fingerprint_present');
$check(str_contains($config, 'MICROGIFTER_MCP_BRIDGE_ENABLED'), 'bridge_feature_flag');
$check(str_contains($config, 'The canonical bridge requires HTTPS'), 'bridge_https_boundary');
$check(str_contains($productDiscovery, 'product_cursor'), 'independent_product_cursor');
$check(str_contains($productDiscovery, 'next_cursor'), 'product_next_cursor');

$score = (int)round((count(array_filter($checks)) / max(1, count($checks))) * 10, 1);
if ($failures !== []) {
    fwrite(STDERR, "MCP Phase 1 canonical bridge validation failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo json_encode([
    'ok' => true,
    'release' => $release['release_key'] ?? null,
    'checks' => count($checks),
    'score' => $score . '/10',
    'sql_required' => false,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
