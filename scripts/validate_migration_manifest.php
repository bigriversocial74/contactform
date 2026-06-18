<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found.');
}

require_once dirname(__DIR__) . '/includes/migrations.php';

$manifest = mg_migration_manifest();
$databaseDir = mg_migration_database_dir();
$ordered = array_values($manifest['ordered_files']);
$manualOnly = array_keys($manifest['manual_only']);

if ($ordered === []) {
    throw new RuntimeException('Migration manifest is empty.');
}
if (count($ordered) !== count(array_unique($ordered))) {
    throw new RuntimeException('Migration manifest contains duplicate filenames.');
}

$registered = array_merge($ordered, $manualOnly);
$discovered = [];
foreach (glob($databaseDir . '/*.sql') ?: [] as $path) {
    $name = basename($path);
    if (str_contains($name, 'combined') || str_contains($name, 'generated') || str_contains($name, 'full_upgrade')) {
        continue;
    }
    $discovered[] = $name;
}
sort($discovered, SORT_STRING);

$missingFiles = array_values(array_diff($registered, $discovered));
$unregisteredFiles = array_values(array_diff($discovered, $registered));
if ($missingFiles !== []) {
    throw new RuntimeException('Manifest files are missing: ' . implode(', ', $missingFiles));
}
if ($unregisteredFiles !== []) {
    throw new RuntimeException('Unregistered SQL files require ordering: ' . implode(', ', $unregisteredFiles));
}

$allKeys = [];
foreach ($ordered as $file) {
    $path = $databaseDir . '/' . $file;
    $sql = file_get_contents($path);
    if (!is_string($sql) || trim($sql) === '') {
        throw new RuntimeException('Unreadable or empty migration: ' . $file);
    }
    foreach (mg_migration_keys_from_sql($sql, $file) as $key) {
        $allKeys[$key][] = $file;
    }
}

$duplicates = [];
foreach ($allKeys as $key => $files) {
    if (count($files) > 1) {
        $duplicates[$key] = $files;
    }
}
if ($duplicates !== []) {
    $messages = [];
    foreach ($duplicates as $key => $files) {
        $messages[] = $key . ': ' . implode(', ', $files);
    }
    throw new RuntimeException('Duplicate migration keys detected: ' . implode(' | ', $messages));
}

$positions = array_flip($ordered);
foreach ($manifest['coverage_markers'] as $marker => $cutoffFile) {
    if (!is_string($marker) || trim($marker) === '') {
        throw new RuntimeException('Migration coverage marker is invalid.');
    }
    if (!array_key_exists($cutoffFile, $positions)) {
        throw new RuntimeException("Coverage marker {$marker} references an unknown cutoff file: {$cutoffFile}");
    }
}

echo 'Migration manifest valid: ' . count($ordered) . ' ordered files, ' . count($allKeys) . " canonical keys.\n";
