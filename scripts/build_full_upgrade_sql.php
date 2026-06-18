<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found.');
}

require_once dirname(__DIR__) . '/includes/migrations.php';

$root = dirname(__DIR__);
$databaseDir = mg_migration_database_dir();
$output = $argv[1] ?? ($root . '/build/microgifter_full_upgrade.sql');
$manifestConfig = mg_migration_manifest();
$orderedMigrations = array_values($manifestConfig['ordered_files']);
$manualOnly = $manifestConfig['manual_only'];

$discovered = [];
foreach (glob($databaseDir . '/*.sql') ?: [] as $path) {
    $name = basename($path);
    if (!str_contains($name, 'combined') && !str_contains($name, 'generated') && !str_contains($name, 'full_upgrade')) {
        $discovered[] = $name;
    }
}
sort($discovered, SORT_STRING);

$registered = array_merge($orderedMigrations, array_keys($manualOnly));
if ($missing = array_values(array_diff($registered, $discovered))) {
    throw new RuntimeException('Registered migration files are missing: ' . implode(', ', $missing));
}
if ($unregistered = array_values(array_diff($discovered, $registered))) {
    throw new RuntimeException('Unregistered migration files require an explicit dependency-order decision: ' . implode(', ', $unregistered));
}

$forbidden = [
    '/\bDROP\s+DATABASE\b/i' => 'DROP DATABASE',
    '/\bDROP\s+TABLE\b/i' => 'DROP TABLE',
    '/\bTRUNCATE\s+TABLE\b/i' => 'TRUNCATE TABLE',
    '/\bDELETE\s+FROM\s+`?(users|roles|permissions|role_permissions|user_roles|user_sessions)`?\b/i' => 'destructive identity delete',
];

$directory = dirname($output);
if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
    throw new RuntimeException('Unable to create output directory: ' . $directory);
}

$handle = fopen($output, 'wb');
if ($handle === false) {
    throw new RuntimeException('Unable to open output file: ' . $output);
}

fwrite($handle, "-- Microgifter complete ordered database upgrade\n");
fwrite($handle, '-- Generated: ' . gmdate('c') . "\n");
fwrite($handle, '-- Included migrations: ' . count($orderedMigrations) . "\n");
fwrite($handle, '-- Manual-only exclusions: ' . count($manualOnly) . "\n");
foreach ($manualOnly as $name => $reason) {
    fwrite($handle, "-- EXCLUDED {$name}: {$reason}\n");
}

fwrite($handle, "\nSET @MG_OLD_FOREIGN_KEY_CHECKS := @@FOREIGN_KEY_CHECKS;\n");
fwrite($handle, "SET @MG_OLD_UNIQUE_CHECKS := @@UNIQUE_CHECKS;\n");
fwrite($handle, "SET FOREIGN_KEY_CHECKS = 0;\nSET UNIQUE_CHECKS = 0;\n\n");
fwrite($handle, "CREATE TABLE IF NOT EXISTS schema_migrations (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,migration_key VARCHAR(190) NOT NULL,description VARCHAR(255) NULL,checksum CHAR(64) NULL,applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY (id),UNIQUE KEY uq_schema_migrations_key (migration_key)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n");
fwrite($handle, "SET @mg_has_schema_description := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='schema_migrations' AND COLUMN_NAME='description');\n");
fwrite($handle, "SET @mg_sql := IF(@mg_has_schema_description=0, 'ALTER TABLE schema_migrations ADD COLUMN description VARCHAR(255) NULL AFTER migration_key', 'SELECT 1');\n");
fwrite($handle, "PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;\n");
fwrite($handle, "SET @mg_has_schema_checksum := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='schema_migrations' AND COLUMN_NAME='checksum');\n");
fwrite($handle, "SET @mg_sql := IF(@mg_has_schema_checksum=0, 'ALTER TABLE schema_migrations ADD COLUMN checksum CHAR(64) NULL AFTER description', 'SELECT 1');\n");
fwrite($handle, "PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;\n\n");

$generatedManifest = [];
try {
    foreach ($orderedMigrations as $name) {
        $path = $databaseDir . '/' . $name;
        $content = file_get_contents($path);
        if (!is_string($content) || trim($content) === '') {
            throw new RuntimeException('Unreadable or empty migration: ' . $name);
        }
        foreach ($forbidden as $pattern => $label) {
            if (preg_match($pattern, $content) === 1) {
                throw new RuntimeException("Forbidden {$label} statement found in {$name}.");
            }
        }

        $checksum = hash('sha256', $content);
        $generatedManifest[] = [
            'file' => $name,
            'keys' => mg_migration_keys_from_sql($content, $name),
            'sha256' => $checksum,
        ];
        fwrite($handle, "-- BEGIN {$name} sha256={$checksum}\n" . rtrim($content) . "\n-- END {$name}\n\n");
    }
    fwrite($handle, "SET FOREIGN_KEY_CHECKS = @MG_OLD_FOREIGN_KEY_CHECKS;\nSET UNIQUE_CHECKS = @MG_OLD_UNIQUE_CHECKS;\n");
} catch (Throwable $error) {
    fclose($handle);
    @unlink($output);
    throw $error;
}

fclose($handle);
$manifestPath = preg_replace('/\.sql$/', '.manifest.json', $output) ?: ($output . '.manifest.json');
$payload = [
    'generated_at' => gmdate('c'),
    'migration_count' => count($generatedManifest),
    'migrations' => $generatedManifest,
    'coverage_markers' => $manifestConfig['coverage_markers'],
    'manual_only_migrations' => array_map(
        static fn(string $reason, string $file): array => ['file' => $file, 'reason' => $reason],
        $manualOnly,
        array_keys($manualOnly)
    ),
];
if (file_put_contents($manifestPath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL) === false) {
    @unlink($output);
    throw new RuntimeException('Unable to write upgrade manifest: ' . $manifestPath);
}

echo "Generated {$output}\nGenerated {$manifestPath}\n";
