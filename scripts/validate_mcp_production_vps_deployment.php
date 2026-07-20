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

$releasePath = $root . '/config/mcp_production_vps_deployment_release.php';
$check(is_file($releasePath), 'release_manifest_exists');
$release = is_file($releasePath) ? require $releasePath : [];
$check(($release['release_key'] ?? null) === 'microgifter_mcp_production_vps_deployment_v1', 'release_key_locked');
$check(($release['required_migrations'] ?? null) === [], 'release_adds_no_sql');
$check(($release['node_engine'] ?? null) === '>=20', 'node_20_locked');
$check(($release['internal_listener'] ?? null) === '127.0.0.1:8787', 'loopback_listener_locked');

$files = [
    'services/mcp/Dockerfile',
    'services/mcp/.dockerignore',
    'services/mcp/src/runtime.ts',
    'services/mcp/src/cli/validateEnvironment.ts',
    'services/mcp/scripts/smoke-test.mjs',
    'services/mcp/tests/runtime.test.mjs',
    'deploy/vps/mcp.env.example',
    'deploy/vps/php-bridge.env.example',
    'deploy/vps/docker-compose.mcp.yml',
    'deploy/vps/systemd/microgifter-mcp.service',
    'deploy/vps/nginx/mcp-bootstrap.conf.template',
    'deploy/vps/nginx/mcp.microgifter.com.conf.template',
    'deploy/vps/scripts/install-systemd.sh',
    'deploy/vps/scripts/activate-systemd.sh',
    'docs/MICROGIFTER_MCP_PRODUCTION_VPS_DEPLOYMENT_V1.md',
    'tests/phpunit/McpProductionVpsDeploymentV1ContractTest.php',
];
foreach ($files as $path) {
    $check(is_file($root . '/' . $path), 'file_' . str_replace(['/', '.', '-'], '_', $path));
}

$app = $source('services/mcp/src/http/app.ts');
$server = $source('services/mcp/src/server.ts');
$config = $source('services/mcp/src/protocolConfig.ts');
$runtime = $source('services/mcp/src/runtime.ts');
$dockerfile = $source('services/mcp/Dockerfile');
$compose = $source('deploy/vps/docker-compose.mcp.yml');
$systemd = $source('deploy/vps/systemd/microgifter-mcp.service');
$nginx = $source('deploy/vps/nginx/mcp.microgifter.com.conf.template');
$installer = $source('deploy/vps/scripts/install-systemd.sh');
$activation = $source('deploy/vps/scripts/activate-systemd.sh');
$smoke = $source('services/mcp/scripts/smoke-test.mjs');
$env = $source('deploy/vps/mcp.env.example');

$check(str_contains($app, 'app.get("/health"'), 'liveness_endpoint');
$check(str_contains($app, 'app.get("/ready"'), 'readiness_endpoint');
$check(str_contains($app, 'runtime.draining'), 'draining_fails_readiness_and_mcp');
$check(str_contains($app, 'bridge.resolveConnection'), 'readiness_rechecks_canonical_authority');
$check(str_contains($server, 'process.once("SIGTERM"'), 'sigterm_handled');
$check(str_contains($server, 'process.once("SIGINT"'), 'sigint_handled');
$check(str_contains($server, 'runtime.waitForIdle'), 'request_drain_wait');
$check(str_contains($server, 'server.closeAllConnections'), 'drain_timeout_force_close');
$check(str_contains($config, 'runtime.environment === "production"'), 'production_validation');
$check(str_contains($config, 'Production MCP requires at least one explicit allowed Host value.'), 'production_allowed_hosts_required');
$check(str_contains($config, 'bind outside loopback'), 'production_loopback_default');
$check(str_contains($runtime, 'sensitiveKeyFragments'), 'structured_log_redaction');
$check(str_contains($runtime, 'Bearer [REDACTED]'), 'bearer_redaction');

$check(substr_count($dockerfile, 'FROM node:20-bookworm-slim') >= 2, 'docker_multi_stage_node20');
$check(str_contains($dockerfile, 'USER node'), 'docker_non_root');
$check(str_contains($dockerfile, 'HEALTHCHECK'), 'docker_healthcheck');
$check(str_contains($compose, '127.0.0.1:8787:8787'), 'compose_loopback_publish');
$check(str_contains($compose, 'read_only: true'), 'compose_read_only');
$check(str_contains($compose, 'cap_drop:'), 'compose_capabilities_dropped');
$check(str_contains($compose, 'no-new-privileges:true'), 'compose_no_new_privileges');

foreach (['NoNewPrivileges=true', 'PrivateTmp=true', 'ProtectSystem=strict', 'ProtectHome=true', 'CapabilityBoundingSet=', 'EnvironmentFile=/etc/microgifter/mcp.env', 'Restart=on-failure'] as $directive) {
    $check(str_contains($systemd, $directive), 'systemd_' . strtolower(preg_replace('/[^A-Za-z0-9]+/', '_', $directive) ?? $directive));
}
$check(str_contains($nginx, 'server 127.0.0.1:8787'), 'nginx_loopback_upstream');
$check(str_contains($nginx, 'proxy_buffering off'), 'nginx_streaming_buffer_disabled');
$check(str_contains($nginx, 'ssl_certificate '), 'nginx_tls');
$check(str_contains($nginx, 'client_max_body_size 1m'), 'nginx_body_limit');

$check(str_contains($installer, 'Node.js 20 or newer is required'), 'installer_node_version_gate');
$check(str_contains($installer, 'chmod 0600 "$ENV_FILE"'), 'installer_secret_file_mode');
$check(str_contains($installer, 'PREVIOUS_TARGET='), 'installer_tracks_rollback');
$check(str_contains($installer, 'previous release restored'), 'installer_automatic_rollback');
$check(str_contains($installer, 'npm run check'), 'installer_tests_release');
$check(str_contains($activation, 'validateEnvironment.js'), 'activation_validates_environment');
$check(str_contains($activation, '127.0.0.1:8787/ready'), 'activation_requires_readiness');

$check(str_contains($smoke, 'MCP_SMOKE_BEARER_TOKEN'), 'smoke_requires_raw_bearer');
$check(str_contains($smoke, 'bearer_token_emitted: false'), 'smoke_never_emits_bearer');
$check(!str_contains($smoke, 'console.log(bearerToken)'), 'smoke_no_bearer_logging');
$check(str_contains($env, 'MICROGIFTER_MCP_ENV=production'), 'environment_template_production');
$check(str_contains($env, 'MICROGIFTER_MCP_ALLOWED_HOSTS=mcp.microgifter.com'), 'environment_template_allowed_host');
$check(!preg_match('/^[^#\n]*(?:token|secret)=((?!REPLACE_).)+$/mi', $env), 'environment_template_contains_no_real_secrets');

$score = round((count(array_filter($checks)) / max(1, count($checks))) * 10, 1);
if ($failures !== []) {
    fwrite(STDERR, "MCP production VPS deployment validation failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo json_encode([
    'ok' => true,
    'release' => $release['release_key'] ?? null,
    'checks' => count($checks),
    'score' => number_format($score, 1) . '/10',
    'sql_required' => false,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
