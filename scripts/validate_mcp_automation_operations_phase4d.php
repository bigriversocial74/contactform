<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit('Not found.'); }
$root = dirname(__DIR__);
$required = [
    'account-agent-automation-operations.php',
    'includes/mcp-automations/operations.php',
    'includes/mcp-automations/operations-page-view.php',
    'assets/css/mcp-automation-operations.css',
    'config/mcp_automation_operations_phase4d_release.php',
    'docs/MICROGIFTER_MCP_AUTOMATION_OPERATIONS_PHASE4D.md',
];
foreach ($required as $path) {
    if (!is_file($root . '/' . $path)) { fwrite(STDERR, "Missing Phase 4D file: $path\n"); exit(1); }
}
$authority = (string)file_get_contents($root . '/includes/mcp-automations/operations.php');
$page = (string)file_get_contents($root . '/account-agent-automation-operations.php') . "\n" . (string)file_get_contents($root . '/includes/mcp-automations/operations-page-view.php');
$loader = (string)file_get_contents($root . '/includes/mcp-automations.php');
$checks = [
    'owner operations snapshot' => str_contains($authority, 'function mg_mcp_automation_owner_operations_snapshot'),
    'owner emergency pause' => str_contains($authority, 'function mg_mcp_automation_emergency_pause_all'),
    'connection pause' => str_contains($authority, 'function mg_mcp_automation_pause_connection'),
    'run cancellation' => str_contains($authority, 'function mg_mcp_automation_request_run_cancellation'),
    'revocation increment' => str_contains($authority, 'revocation_version=revocation_version+1'),
    'due clearing' => str_contains($authority, 'next_due_at=NULL'),
    'cancellation marker' => str_contains($authority, 'cancellation_requested_at=COALESCE'),
    'csrf' => str_contains($page, 'mg_verify_csrf'),
    'operations navigation' => str_contains($page, '/account-agent-automation-operations.php'),
    'loader integration' => str_contains($loader, 'mcp-automations/operations.php'),
    'execution disabled wording' => str_contains($page, 'execution-disabled'),
];
foreach ($checks as $label => $passed) {
    if (!$passed) { fwrite(STDERR, "Phase 4D validation failed: $label\n"); exit(1); }
}
if (str_contains($authority, 'INSERT INTO mcp_action_receipts')) { fwrite(STDERR, "Phase 4D must not create action receipts.\n"); exit(1); }
$release = require $root . '/config/mcp_automation_operations_phase4d_release.php';
if (($release['new_migrations'] ?? null) !== [] || ($release['runtime_execution_enabled'] ?? true) !== false || ($release['worker_enabled'] ?? true) !== false) {
    fwrite(STDERR, "Phase 4D release boundary is invalid.\n"); exit(1);
}
echo "MCP automation operations Phase 4D validation passed.\n";
