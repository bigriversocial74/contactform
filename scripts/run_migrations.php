<?php
/**
 * Canonical Microgifter schema migration runner.
 *
 * Usage:
 *   php scripts/run_migrations.php
 *
 * MySQL implicitly commits many DDL statements, so migrations are not wrapped in
 * a PDO transaction. A database advisory lock prevents concurrent runners.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found.');
}

require_once dirname(__DIR__) . '/includes/migrations.php';

$config = require dirname(__DIR__) . '/api/config.php';
$db = $config['db'];
$migrationUser = getenv('MG_MIGRATION_DB_USER');
$migrationPass = getenv('MG_MIGRATION_DB_PASS');
if (is_string($migrationUser) && $migrationUser !== '') {
    $db['user'] = $migrationUser;
}
if (is_string($migrationPass)) {
    $db['pass'] = $migrationPass;
}

$dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $db['host'], $db['name'], $db['charset']);
$pdo = new PDO($dsn, $db['user'], $db['pass'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);

$manifest = mg_migration_manifest();
$databaseDir = mg_migration_database_dir();
$files = array_values($manifest['ordered_files']);
$lockName = 'microgifter_schema_migrations';
$exitCode = 0;

$lockStmt = $pdo->prepare('SELECT GET_LOCK(?, 30)');
$lockStmt->execute([$lockName]);
if ((int) $lockStmt->fetchColumn() !== 1) {
    fwrite(STDERR, "Could not acquire schema migration lock.\n");
    exit(1);
}

try {
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS schema_migrations (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            migration_key VARCHAR(190) NOT NULL,
            description VARCHAR(255) NULL,
            checksum CHAR(64) NULL,
            applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_schema_migrations_key (migration_key),
            INDEX idx_schema_migrations_applied_at (applied_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $columns = $pdo->query('SHOW COLUMNS FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('description', $columns, true)) {
        $pdo->exec('ALTER TABLE schema_migrations ADD COLUMN description VARCHAR(255) NULL AFTER migration_key');
    }
    if (!in_array('checksum', $columns, true)) {
        $pdo->exec('ALTER TABLE schema_migrations ADD COLUMN checksum CHAR(64) NULL AFTER description');
    }

    $applied = mg_migration_applied_rows($pdo);
    $coverageCutoff = mg_migration_coverage_cutoff($applied, $manifest);
    $record = $pdo->prepare(
        'INSERT INTO schema_migrations (migration_key, description, checksum, applied_at)
         VALUES (?, ?, ?, NOW())
         ON DUPLICATE KEY UPDATE checksum = VALUES(checksum), description = COALESCE(schema_migrations.description, VALUES(description))'
    );

    foreach ($files as $index => $file) {
        $path = $databaseDir . '/' . $file;
        if (!is_file($path)) {
            throw new RuntimeException("Missing migration file: {$file}");
        }

        $sql = file_get_contents($path);
        if (!is_string($sql) || trim($sql) === '') {
            throw new RuntimeException("Empty migration file: {$file}");
        }

        $keys = mg_migration_keys_from_sql($sql, $file);
        $checksum = hash('sha256', $sql);

        if ($index <= $coverageCutoff) {
            echo "SKIP {$file} (covered by consolidated baseline)\n";
            continue;
        }

        $presentKeys = array_values(array_filter($keys, static fn(string $key): bool => array_key_exists($key, $applied)));
        if ($presentKeys !== [] && count($presentKeys) !== count($keys)) {
            throw new RuntimeException("Partially recorded migration: {$file}");
        }

        if (count($presentKeys) === count($keys)) {
            foreach ($keys as $key) {
                $storedChecksum = $applied[$key];
                if (is_string($storedChecksum) && $storedChecksum !== '' && !hash_equals($storedChecksum, $checksum)) {
                    throw new RuntimeException("Checksum mismatch for already-applied migration: {$file} ({$key})");
                }
                if ($storedChecksum === null || $storedChecksum === '') {
                    $record->execute([$key, "Applied by {$file}", $checksum]);
                    $applied[$key] = $checksum;
                }
            }
            echo "SKIP {$file}\n";
            continue;
        }

        echo "APPLY {$file}\n";
        $pdo->exec($sql);
        foreach ($keys as $key) {
            $record->execute([$key, "Applied by {$file}", $checksum]);
            $applied[$key] = $checksum;
        }
    }

    $status = mg_migration_status($pdo, $databaseDir);
    if (!$status['ready']) {
        throw new RuntimeException('Migration runner completed but the canonical manifest is not fully satisfied.');
    }

    echo 'Migrations complete: ' . count($files) . " canonical files satisfied.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'FAILED: ' . $e->getMessage() . "\n");
    $exitCode = 1;
} finally {
    try {
        $releaseStmt = $pdo->prepare('SELECT RELEASE_LOCK(?)');
        $releaseStmt->execute([$lockName]);
    } catch (Throwable) {
        fwrite(STDERR, "Warning: could not release schema migration lock.\n");
    }
}

exit($exitCode);
