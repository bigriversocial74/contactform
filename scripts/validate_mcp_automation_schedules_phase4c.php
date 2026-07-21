<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit('Not found.'); }
$root = dirname(__DIR__);
$required = [
    'account-agent-automation-schedules.php',
    'includes/mcp-automations/schedules.php',
    'includes/mcp-automations/schedules-page-view.php',
    'assets/css/mcp-automation-schedules.css',
    'config/mcp_automation_schedules_phase4c_release.php',
    'docs/MICROGIFTER_MCP_AUTOMATION_SCHEDULES_PHASE4C.md',
    'scripts/test_mcp_automation_schedules_phase4c.php',
    'tests/phpunit/McpAutomationSchedulesPhase4cV1ContractTest.php',
    '.github/workflows/mcp-automation-schedules-phase4c.yml',
    'database/20260720_microgifter_mcp_automation_foundation_v1.sql',
];
$ok = true;
$files = [];
foreach ($required as $path) {
    $exists = is_file($root . '/' . $path);
    $files[] = ['path' => $path, 'exists' => $exists];
    $ok = $ok && $exists;
}
$serviceFiles = array_merge([$root . '/includes/mcp-automations.php'], glob($root . '/includes/mcp-automations/*.php') ?: []);
$service = implode("\n", array_map(static fn(string $file): string => is_file($file) ? (string)file_get_contents($file) : '', $serviceFiles));
$page = (string)@file_get_contents($root . '/account-agent-automation-schedules.php') . "\n" . (string)@file_get_contents($root . '/includes/mcp-automations/schedules-page-view.php');
$release = (string)@file_get_contents($root . '/config/mcp_automation_schedules_phase4c_release.php');
$docs = (string)@file_get_contents($root . '/docs/MICROGIFTER_MCP_AUTOMATION_SCHEDULES_PHASE4C.md');
$simulation = (string)@file_get_contents($root . '/includes/mcp-automations/simulations.php');
$checks = [
    'explicit_schedule_authority' => str_contains($service, 'mg_mcp_automation_update_schedule_authority')
        && str_contains($service, 'allowed_trigger_types_json')
        && str_contains($service, 'MCP_AUTOMATION_TRIGGER_DENIED'),
    'fixed_and_recurring_only' => str_contains($service, "['fixed_schedule', 'recurring_schedule']")
        && str_contains($release, "'allowed_trigger_types' => ['manual', 'fixed_schedule', 'recurring_schedule']"),
    'timezone_and_interval_bounds' => str_contains($service, 'MG_MCP_AUTOMATION_SCHEDULE_TIMEZONES')
        && str_contains($service, 'MG_MCP_AUTOMATION_SCHEDULE_INTERVALS')
        && str_contains($release, "'minimum_recurring_interval_seconds' => 3600"),
    'manual_due_evaluator' => str_contains($service, 'mg_mcp_automation_evaluate_due_schedules')
        && str_contains($page, 'Evaluate due simulations')
        && str_contains($release, "'trigger_fire_mode' => 'owner_manual_due_evaluation'"),
    'no_background_scheduler' => str_contains($page, 'No background scheduler exists in Phase 4C')
        && str_contains($release, "'background_scheduler_enabled' => false")
        && str_contains($docs, 'Nothing runs in the background.'),
    'scheduled_simulation_mode' => str_contains($simulation, "'scheduled_simulation_only'")
        && str_contains($simulation, "'proposed',1")
        && str_contains($simulation, "'action_receipts_created' => 0"),
    'zero_receipt_inserts' => !str_contains($simulation, 'INSERT INTO mcp_action_receipts'),
    'idempotent_due_firing' => str_contains($simulation, "'phase4c-sim:'")
        && str_contains($simulation, "trigger['public_id']")
        && str_contains($simulation, '$dueAt'),
    'fixed_expires_recurring_advances' => str_contains($simulation, "'expired'")
        && str_contains($simulation, 'mg_mcp_automation_next_recurring_due'),
    'runtime_disabled' => str_contains($release, "'runtime_execution_enabled' => false")
        && str_contains($release, "'worker_enabled' => false")
        && str_contains($page, 'zero action receipts'),
    'no_new_sql' => str_contains($release, "'new_migrations' => []")
        && !is_file($root . '/database/20260721_mcp_automation_schedules_phase4c_v1.sql'),
];
foreach ($checks as $passed) { $ok = $ok && $passed; }
echo json_encode(['ok' => $ok, 'files' => $files, 'checks' => $checks], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit($ok ? 0 : 1);
