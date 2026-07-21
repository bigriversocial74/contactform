<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found.');
}

$root = dirname(__DIR__);
$required = [
    'account-agent-automations.php',
    'includes/mcp-automations.php',
    'assets/css/mcp-automation-grants.css',
    'config/mcp_automation_grants_phase4a_release.php',
    'docs/MICROGIFTER_MCP_AUTOMATION_GRANTS_PHASE4A.md',
    'docs/MICROGIFTER_MCP_INSTALLATION_AND_ACTIVATION.md',
    'tests/phpunit/McpAutomationGrantsPhase4aV1ContractTest.php',
    '.github/workflows/mcp-automation-grants-phase4a.yml',
    'database/20260720_microgifter_mcp_automation_foundation_v1.sql',
];

$files = [];
$ok = true;
foreach ($required as $path) {
    $exists = is_file($root . '/' . $path);
    $files[] = ['path' => $path, 'exists' => $exists];
    $ok = $ok && $exists;
}

$serviceFiles = array_merge([$root . '/includes/mcp-automations.php'], glob($root . '/includes/mcp-automations/*.php') ?: []);
$service = implode("\n", array_map(static fn(string $file): string => is_file($file) ? (string)file_get_contents($file) : '', $serviceFiles));
$pageFiles = [$root . '/account-agent-automations.php', $root . '/includes/mcp-automations/account-page-view.php'];
$page = implode("\n", array_map(static fn(string $file): string => is_file($file) ? (string)file_get_contents($file) : '', $pageFiles));
$release = is_file($root . '/config/mcp_automation_grants_phase4a_release.php') ? (string)file_get_contents($root . '/config/mcp_automation_grants_phase4a_release.php') : '';
$docs = is_file($root . '/docs/MICROGIFTER_MCP_AUTOMATION_GRANTS_PHASE4A.md') ? (string)file_get_contents($root . '/docs/MICROGIFTER_MCP_AUTOMATION_GRANTS_PHASE4A.md') : '';
$install = is_file($root . '/docs/MICROGIFTER_MCP_INSTALLATION_AND_ACTIVATION.md') ? (string)file_get_contents($root . '/docs/MICROGIFTER_MCP_INSTALLATION_AND_ACTIVATION.md') : '';
$sql = is_file($root . '/database/20260720_microgifter_mcp_automation_foundation_v1.sql') ? (string)file_get_contents($root . '/database/20260720_microgifter_mcp_automation_foundation_v1.sql') : '';

$checks = [
    'uses_existing_grant_table' => str_contains($sql, 'CREATE TABLE IF NOT EXISTS mcp_automation_grants')
        && str_contains($service, 'INSERT INTO mcp_automation_grants'),
    'owner_scoped' => str_contains($service, 'g.authorizing_user_id=?')
        && str_contains($service, 'c.user_id=?'),
    'fixed_playbooks' => str_contains($service, 'mg_mcp_automation_playbook_catalog')
        && str_contains($service, 'An unrecognized playbook was selected.')
        && str_contains($page, 'Arbitrary tool names are never accepted'),
    'scope_revalidation' => str_contains($service, 'mg_mcp_automation_connection_scopes')
        && str_contains($service, 'MCP_AUTOMATION_SCOPE_REVOKED'),
    'workspace_revalidation' => str_contains($service, 'merchant_team_members')
        && str_contains($service, 'MCP_AUTOMATION_WORKSPACE_ACCESS_REVOKED'),
    'operation_ceiling' => str_contains($service, 'MCP_AUTOMATION_OPERATION_CEILING')
        && str_contains($service, 'mg_mcp_automation_operation_rank'),
    'bounded_limits' => str_contains($service, 'per_run_amount_limit_cents')
        && str_contains($service, 'daily_amount_limit_cents')
        && str_contains($service, 'lifetime_amount_limit_cents')
        && str_contains($service, 'minimum_frequency_seconds')
        && str_contains($service, 'maximum_concurrent_runs'),
    'target_policy' => str_contains($service, 'allowed_product_ids')
        && str_contains($service, 'allowed_campaign_ids')
        && str_contains($service, 'allowed_reward_template_ids')
        && str_contains($service, 'MCP_AUTOMATION_TARGET_DENIED'),
    'lifecycle_controls' => str_contains($service, "'draft' => ['active', 'revoked']")
        && str_contains($service, "'active' => ['paused', 'revoked']")
        && str_contains($service, "'paused' => ['active', 'revoked']"),
    'revocation_cancels_future_work' => str_contains($service, 'revocation_version=revocation_version+1')
        && str_contains($service, 'cancellation_requested_at=COALESCE'),
    'security_evidence' => str_contains($service, 'INSERT INTO mcp_security_events')
        && str_contains($service, "mg_audit('mcp_automation_grant_")
        && str_contains($service, "mg_event('mcp.automation_grant."),
    'future_worker_guard' => str_contains($service, 'function mg_mcp_automation_authorize_grant_action')
        && str_contains($service, "'execution_enabled' => false"),
    'manual_only' => str_contains($service, "json_encode(['manual']")
        && str_contains($release, "'allowed_trigger_types' => ['manual']"),
    'approval_always' => str_contains($service, "'always'")
        && str_contains($release, "'approval_policy' => 'always'"),
    'runtime_disabled' => str_contains($page, 'Node.js, public MCP transport, security keys, schedulers, queues, workers, and action execution remain disabled')
        && str_contains($release, "'runtime_execution_enabled' => false")
        && str_contains($docs, 'Runtime execution remains disabled even when a grant is active.')
        && str_contains($install, 'Current PHP-hosting deployment')
        && str_contains($install, '/account-agent-automations.php'),
    'no_new_sql' => str_contains($release, "'new_migrations' => []")
        && !is_file($root . '/database/20260721_mcp_automation_grants_phase4a_v1.sql'),
];

foreach ($checks as $passed) {
    $ok = $ok && $passed;
}

echo json_encode([
    'ok' => $ok,
    'files' => $files,
    'checks' => $checks,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

exit($ok ? 0 : 1);
