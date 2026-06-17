<?php
/**
 * Microgifter schema migration runner.
 *
 * Usage:
 *   php scripts/run_migrations.php
 *
 * MySQL implicitly commits many DDL statements, so migrations are not wrapped in a
 * PDO transaction. A database advisory lock prevents concurrent runners. The runner
 * uses the repository's existing schema_migrations.migration_key format and stores
 * checksums after each migration succeeds.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found.');
}

require_once dirname(__DIR__) . '/api/db.php';

$root = dirname(__DIR__);
$databaseDir = $root . '/database';
$files = [
    'stage_1_identity.sql',
    'stage_1_repair_03M.sql',
    'stage_1_security_hardening_03N.sql',
    'stage_1_security_hardening_03N_3.sql',
    'stage_1_foundation_closure.sql',
    'stage_3_agent_persistence.sql',
    'stage_3_gift_activity_persistence.sql',
    'stage_3_gift_lifecycle.sql',
    'stage_3_merchant_claim_codes.sql',
    'stage_3_pppm_core.sql',
    'stage_3_pppm_activity_layer.sql',
];

$pdo = mg_db();
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

    $select = $pdo->prepare('SELECT checksum FROM schema_migrations WHERE migration_key = ? LIMIT 1');
    $record = $pdo->prepare(
        'INSERT INTO schema_migrations (migration_key, description, checksum, applied_at)
         VALUES (?, ?, ?, NOW())
         ON DUPLICATE KEY UPDATE checksum = VALUES(checksum), description = COALESCE(schema_migrations.description, VALUES(description))'
    );

    foreach ($files as $file) {
        $path = $databaseDir . '/' . $file;
        if (!is_file($path)) {
            throw new RuntimeException("Missing migration file: {$file}");
        }
        $sql = file_get_contents($path);
        if (!is_string($sql) || trim($sql) === '') {
            throw new RuntimeException("Empty migration file: {$file}");
        }
        $checksum = hash('sha256', $sql);
        $select->execute([$file]);
        $existing = $select->fetchColumn();
        if (is_string($existing) && $existing !== '') {
            if (!hash_equals($existing, $checksum)) {
                throw new RuntimeException("Checksum mismatch for already-applied migration: {$file}");
            }
            echo "SKIP {$file}\n";
            continue;
        }
        echo "APPLY {$file}\n";
        $pdo->exec($sql);
        $record->execute([$file, 'Applied by scripts/run_migrations.php', $checksum]);
    }
    echo "Migrations complete.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'FAILED: ' . $e->getMessage() . "\n");
    $exitCode = 1;
} finally {
    try {
        $releaseStmt = $pdo->prepare('SELECT RELEASE_LOCK(?)');
        $releaseStmt->execute([$lockName]);
    } catch (Throwable $releaseError) {
        fwrite(STDERR, "Warning: could not release schema migration lock.\n");
    }
}

exit($exitCode);
