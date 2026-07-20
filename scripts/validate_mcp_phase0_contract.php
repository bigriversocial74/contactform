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

$read = static function (string $path) use ($root): string {
    $full = $root . '/' . ltrim($path, '/');
    if (!is_file($full)) {
        return '';
    }
    $content = file_get_contents($full);
    return is_string($content) ? $content : '';
};

$manifestPath = $root . '/config/mcp_phase0_release.php';
$check(is_file($manifestPath), 'release_manifest_exists');
$manifest = is_file($manifestPath) ? require $manifestPath : [];
$check(is_array($manifest), 'release_manifest_is_array');

$check(($manifest['release_key'] ?? null) === 'microgifter_mcp_phase0_contract_v1', 'release_key_locked');
$check(($manifest['program'] ?? null) === 'microgifter_platform_phase5', 'platform_program_locked');
$check(($manifest['runtime_enabled'] ?? true) === false, 'phase0_runtime_disabled');
$check(($manifest['sql_required'] ?? true) === false, 'phase0_requires_no_sql');
$check(in_array('task_agent_phase4_v1', (array)($manifest['depends_on'] ?? []), true), 'phase4_dependency_declared');

$requiredDocuments = [
    'docs/MICROGIFTER_MCP_AUTOMATION_PLATFORM_SPEC.md',
    'docs/MICROGIFTER_MCP_PHASE0_ARCHITECTURE_CONTRACT_LOCK.md',
];
foreach ($requiredDocuments as $document) {
    $check(in_array($document, (array)($manifest['documents'] ?? []), true), 'manifest_lists_' . basename($document));
    $check(is_file($root . '/' . $document), 'document_exists_' . basename($document));
}

$spec = $read($requiredDocuments[0]);
$contract = $read($requiredDocuments[1]);
$requiredSpecPhrases = [
    'read-only first; automation-capable by design',
    'Microgifter remains the system of record',
    'durable automation grant',
    'There is no administrator bypass',
    'The TypeScript service receives no production database credentials',
    'There is no unbounded autonomous mode',
    'microgifter.account.get_connection_context',
    'microgifter.catalog.search',
    'microgifter.catalog.get_item',
];
foreach ($requiredSpecPhrases as $index => $phrase) {
    $check(str_contains($spec, $phrase), 'spec_phrase_' . ($index + 1), $phrase);
}

$requiredContractPhrases = [
    'MCP tool or automation action',
    'protected internal PHP bridge',
    'OAuth authority cannot exceed',
    'No unattended execution is valid without an active grant',
    'The existing Approval Center remains canonical',
    'database/20260720_microgifter_mcp_automation_foundation_v1.sql',
    'No runtime endpoint or SQL is introduced by Phase 0',
];
foreach ($requiredContractPhrases as $index => $phrase) {
    $check(str_contains($contract, $phrase), 'contract_phrase_' . ($index + 1), $phrase);
}

$expectedClasses = ['read', 'monitor', 'recommend', 'task', 'draft', 'approval_gated', 'bounded_auto', 'prohibited'];
$classes = array_values((array)($manifest['operation_classes'] ?? []));
$check($classes === $expectedClasses, 'operation_classes_locked');

$expectedTools = [
    'microgifter.account.get_connection_context' => 'profile:read',
    'microgifter.catalog.search' => 'catalog:read',
    'microgifter.catalog.get_item' => 'catalog:read',
];
$tools = (array)($manifest['initial_tools'] ?? []);
$check(array_keys($tools) === array_keys($expectedTools), 'initial_tool_names_locked');
foreach ($expectedTools as $tool => $scope) {
    $definition = is_array($tools[$tool] ?? null) ? $tools[$tool] : [];
    $check(($definition['operation_class'] ?? null) === 'read', 'initial_tool_read_only_' . str_replace('.', '_', $tool));
    $check(($definition['scope'] ?? null) === $scope, 'initial_tool_scope_' . str_replace('.', '_', $tool));
}
$check((int)($tools['microgifter.catalog.search']['maximum_page_size'] ?? 0) === 25, 'catalog_search_page_limit_locked');

$serviceBoundary = (array)($manifest['service_boundary'] ?? []);
$check(($serviceBoundary['gateway'] ?? null) === 'services/mcp/', 'typescript_gateway_location_locked');
$check(($serviceBoundary['gateway_runtime'] ?? null) === 'typescript_node', 'typescript_gateway_runtime_locked');
$check(($serviceBoundary['database_access'] ?? null) === 'php_canonical_services_only', 'node_database_access_prohibited');

$requiredFoundation = [
    'durable_grants',
    'automation_definitions',
    'trigger_contracts',
    'scheduler_interfaces',
    'queue_and_worker_interfaces',
    'run_and_action_state_machines',
    'approval_linkage',
    'fresh_state_validation',
    'idempotency',
    'budgets_and_limits',
    'invocation_and_action_receipts',
    'revocation_and_kill_switches',
];
$foundation = (array)($manifest['automation_foundation'] ?? []);
$check(array_diff($requiredFoundation, $foundation) === [], 'automation_foundation_complete');

$requiredBoundaries = [
    'read_only_first_automation_capable_by_design',
    'microgifter_remains_domain_and_execution_authority',
    'oauth_scope_is_necessary_but_not_sufficient',
    'unattended_execution_requires_active_durable_grant',
    'node_gateway_has_no_database_credentials',
    'protected_php_bridge_calls_canonical_services',
    'browser_session_and_csrf_controls_remain_unchanged',
    'no_generic_sql_file_shell_callback_webhook_or_url_tools',
    'no_unbounded_autonomous_mode',
    'no_phase0_runtime_endpoint',
    'no_phase0_sql',
];
$boundaries = (array)($manifest['release_boundaries'] ?? []);
$check(array_diff($requiredBoundaries, $boundaries) === [], 'release_boundaries_complete');

$canonicalFiles = [
    'config/task_agent_phase4_release.php',
    'config/migrations.php',
    'api/bootstrap.php',
    'api/catalog/_catalog.php',
    'includes/public-product-foundation.php',
    'api/public/product.php',
    'api/agents/_execution.php',
    'api/agents/_workflow.php',
];
foreach ($canonicalFiles as $path) {
    $check(is_file($root . '/' . $path), 'canonical_file_exists_' . str_replace(['/', '.'], '_', $path));
}

$phase4 = is_file($root . '/config/task_agent_phase4_release.php')
    ? require $root . '/config/task_agent_phase4_release.php'
    : [];
$check(($phase4['release_key'] ?? null) === 'task_agent_phase4_v1', 'phase4_release_key_confirmed');
$check(in_array('20260720_task_agent_phase4_v1.sql', (array)($phase4['required_migrations'] ?? []), true), 'phase4_migration_confirmed');

$migrations = is_file($root . '/config/migrations.php')
    ? require $root . '/config/migrations.php'
    : [];
$ordered = array_values((array)($migrations['ordered_files'] ?? []));
$phase4Index = array_search('20260720_task_agent_phase4_v1.sql', $ordered, true);
$check($phase4Index !== false, 'phase4_migration_registered');

$plannedMigration = (string)($manifest['planned_phase1_migration'] ?? '');
$check($plannedMigration === '20260720_microgifter_mcp_automation_foundation_v1.sql', 'phase1_migration_name_locked');
$plannedIndex = array_search($plannedMigration, $ordered, true);
if ($plannedIndex !== false && $phase4Index !== false) {
    $check($plannedIndex > $phase4Index, 'future_phase1_migration_ordered_after_phase4');
}

$result = [
    'suite' => 'microgifter_mcp_phase0_architecture_contract_v1',
    'score' => $failures === [] ? '10.0/10' : 'failed',
    'checks' => $checks,
    'failures' => $failures,
];

if ($failures !== []) {
    fwrite(STDERR, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    exit(1);
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
