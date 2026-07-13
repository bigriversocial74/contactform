<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$files = [
    'api/admin/_system_health_runtime_checks.php' => file_get_contents($root . '/api/admin/_system_health_runtime_checks.php'),
    'api/admin/_system_health_metrics.php' => file_get_contents($root . '/api/admin/_system_health_metrics.php'),
    'api/admin/_system_health.php' => file_get_contents($root . '/api/admin/_system_health.php'),
];

foreach ($files as $path => $content) {
    if (!is_string($content) || trim($content) === '') {
        fwrite(STDERR, "Missing validation target: {$path}\n");
        exit(1);
    }
}

$checks = [
    'runtime probe helper exists' => str_contains($files['api/admin/_system_health_runtime_checks.php'], 'function mg_admin_system_health_runtime_probe'),
    'runtime catalog exists' => str_contains($files['api/admin/_system_health_runtime_checks.php'], 'function mg_admin_system_health_runtime_checks'),
    'integrity schema warning is mapped' => str_contains($files['api/admin/_system_health_runtime_checks.php'], "'loyalty.quest.integrity_schema_missing'"),
    'integrity pepper warning is mapped' => str_contains($files['api/admin/_system_health_runtime_checks.php'], "'loyalty.quest.integrity_pepper_missing'"),
    'public quest warning is mapped' => str_contains($files['api/admin/_system_health_runtime_checks.php'], "'public.loyalty_quests.unavailable'"),
    'account quest warning is mapped' => str_contains($files['api/admin/_system_health_runtime_checks.php'], "'account.loyalty_quests.unavailable'"),
    'admin quest warning is mapped' => str_contains($files['api/admin/_system_health_runtime_checks.php'], "'admin.loyalty_quest_operations_load_failed'"),
    'ads catalog warning is mapped' => str_contains($files['api/admin/_system_health_runtime_checks.php'], "'ads.picker_catalog_products_failed'"),
    'public marketplace query is compiled' => str_contains($files['api/admin/_system_health_runtime_checks.php'], 'Public Loyalty Quest marketplace'),
    'account portfolio query is compiled' => str_contains($files['api/admin/_system_health_runtime_checks.php'], 'Participant Loyalty Quest portfolio'),
    'admin operations functions are exercised' => str_contains($files['api/admin/_system_health_runtime_checks.php'], "mg_lqo_campaigns(\$pdo, 'all', '', 1)"),
    'ads catalog query is exercised' => str_contains($files['api/admin/_system_health_runtime_checks.php'], 'catalog_product_version_assets'),
    'warning feed separates active and resolved' => str_contains($files['api/admin/_system_health_metrics.php'], "'active' => array_slice(\$active, 0, \$limit)")
        && str_contains($files['api/admin/_system_health_metrics.php'], "'resolved' => array_slice(\$resolved, 0, \$limit)"),
    'warning feed groups duplicate events' => str_contains($files['api/admin/_system_health_metrics.php'], 'function mg_admin_system_health_warning_group'),
    'expired sensitive tokens leave active window' => str_contains($files['api/admin/_system_health_metrics.php'], 'admin.system_health.sensitive_token_invalid')
        && str_contains($files['api/admin/_system_health_metrics.php'], '$age > 900'),
    'security logs are not deleted' => !preg_match('/\b(?:DELETE|TRUNCATE)\s+(?:FROM\s+)?security_logs\b/i', $files['api/admin/_system_health_metrics.php']),
    'runtime service receives probe state' => str_contains($files['api/admin/_system_health.php'], "'loyalty_quests_ready'")
        && str_contains($files['api/admin/_system_health.php'], "'campaign_ads_catalog_ready'"),
    'system health response includes resolved history' => str_contains($files['api/admin/_system_health.php'], "'resolved_warnings' => \$warningFeed['resolved']"),
    'system health response includes warning summary' => str_contains($files['api/admin/_system_health.php'], "'warning_summary' => \$warningFeed['summary']"),
    'runtime checks are exposed to administrators' => str_contains($files['api/admin/_system_health.php'], "'runtime_checks' => \$runtimeChecks"),
];

$failed = array_keys(array_filter($checks, static fn(bool $passed): bool => !$passed));
foreach ($checks as $label => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
}

if ($failed !== []) {
    fwrite(STDERR, 'System Health runtime warning lifecycle validation failed: ' . implode(', ', $failed) . PHP_EOL);
    exit(1);
}

echo 'System Health runtime warning lifecycle contract: 20/20 checks passed.' . PHP_EOL;
