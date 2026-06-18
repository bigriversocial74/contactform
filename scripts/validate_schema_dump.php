<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found.');
}

require_once dirname(__DIR__) . '/includes/migrations.php';

$path = $argv[1] ?? '';
if ($path === '' || !is_file($path)) {
    fwrite(STDERR, "Usage: php scripts/validate_schema_dump.php /path/to/schema.sql\n");
    exit(2);
}

$sql = file_get_contents($path);
if (!is_string($sql) || trim($sql) === '') {
    throw new RuntimeException('Schema dump is unreadable or empty.');
}

preg_match_all('/CREATE\s+TABLE\s+`([^`]+)`/i', $sql, $tableMatches);
$tables = array_values(array_unique($tableMatches[1] ?? []));

$appliedKeys = [];
if (preg_match('/INSERT\s+INTO\s+`schema_migrations`.*?VALUES\s*(.*?);/is', $sql, $insertMatch) === 1) {
    preg_match_all("/\\(\\s*\\d+\\s*,\\s*'([^']+)'/", $insertMatch[1], $keyMatches);
    foreach ($keyMatches[1] ?? [] as $key) {
        $appliedKeys[(string) $key] = null;
    }
}

$manifest = mg_migration_manifest();
$coverageCutoff = mg_migration_coverage_cutoff($appliedKeys, $manifest);
$databaseDir = mg_migration_database_dir();
$missing = [];

foreach (array_values($manifest['ordered_files']) as $index => $file) {
    if ($index <= $coverageCutoff) {
        continue;
    }
    $migrationSql = file_get_contents($databaseDir . '/' . $file);
    if (!is_string($migrationSql) || trim($migrationSql) === '') {
        $missing[] = $file . ' (migration file unavailable)';
        continue;
    }
    $keys = mg_migration_keys_from_sql($migrationSql, $file);
    foreach ($keys as $key) {
        if (!array_key_exists($key, $appliedKeys)) {
            $missing[] = $file . ' (' . $key . ')';
        }
    }
}

$requiredTables = [
    'users', 'commerce_orders', 'pppm_items', 'entitlements',
    'microgift_instances', 'microgift_inbox_items', 'microgift_redemptions',
    'merchant_locations', 'tips', 'subscriptions', 'posts',
    'demand_signal_orchestrations', 'operational_incidents',
    'profile_moderation_cases', 'social_mutation_requests',
];
$missingTables = array_values(array_diff($requiredTables, $tables));

$result = [
    'table_count' => count($tables),
    'migration_key_count' => count($appliedKeys),
    'missing_migrations' => $missing,
    'missing_required_tables' => $missingTables,
    'valid' => $missing === [] && $missingTables === [],
];

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
exit($result['valid'] ? 0 : 1);
