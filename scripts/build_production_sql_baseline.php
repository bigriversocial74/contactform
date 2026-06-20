<?php
declare(strict_types=1);

/**
 * Build the exact pre-bundle database baseline used to validate
 * microgifter_complete_production_update_v1c_v1release.sql.
 *
 * This script is intentionally test-only. It applies every canonical migration
 * except the four migrations consolidated into the production SQL bundle.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found.');
}

require_once dirname(__DIR__) . '/includes/migrations.php';

const MG_PRODUCTION_BUNDLE_EXCLUDED_FILES = [
    'stage_v1c_checkout_session_intent_authority.sql',
    'stage_v1d_transfer_conversations.sql',
    'stage_v1f_stripe_payments.sql',
    'stage_v1_release_trigger_portability.sql',
];

$options = getopt('', ['output::']);
$outputPath = isset($options['output']) && is_string($options['output'])
    ? trim($options['output'])
    : '';

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

$databaseName = (string)($db['name'] ?? '');
if ($databaseName === '' || preg_match('/^[A-Za-z0-9_]+$/', $databaseName) !== 1) {
    throw new RuntimeException('A safe MG_DB_NAME is required for production bundle baseline validation.');
}

$dsn = sprintf(
    'mysql:host=%s;dbname=%s;charset=%s',
    (string)$db['host'],
    $databaseName,
    (string)$db['charset']
);
$pdo = new PDO($dsn, (string)$db['user'], (string)$db['pass'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);

$tableCountStmt = $pdo->prepare(
    'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?'
);
$tableCountStmt->execute([$databaseName]);
if ((int)$tableCountStmt->fetchColumn() !== 0) {
    throw new RuntimeException('Production bundle baseline database must be empty before it is built.');
}

$manifest = mg_migration_manifest();
$databaseDir = mg_migration_database_dir();
$excluded = array_fill_keys(MG_PRODUCTION_BUNDLE_EXCLUDED_FILES, true);
$orderedFiles = array_values($manifest['ordered_files']);
$unknownExcluded = array_values(array_diff(MG_PRODUCTION_BUNDLE_EXCLUDED_FILES, $orderedFiles));
if ($unknownExcluded !== []) {
    throw new RuntimeException('Production bundle exclusion is not present in the canonical manifest: ' . implode(', ', $unknownExcluded));
}

$lockName = 'microgifter_production_bundle_baseline_' . hash('sha256', $databaseName);
$lockStmt = $pdo->prepare('SELECT GET_LOCK(?, 30)');
$lockStmt->execute([$lockName]);
if ((int)$lockStmt->fetchColumn() !== 1) {
    throw new RuntimeException('Could not acquire the production bundle baseline lock.');
}

$appliedFiles = [];
$appliedKeys = [];
$skippedFiles = [];
$startedAt = gmdate('c');

try {
    $pdo->exec(
        'CREATE TABLE schema_migrations (
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

    $record = $pdo->prepare(
        'INSERT INTO schema_migrations (migration_key, description, checksum, applied_at)
         VALUES (?, ?, ?, NOW())
         ON DUPLICATE KEY UPDATE
           description = VALUES(description),
           checksum = VALUES(checksum)'
    );

    foreach ($orderedFiles as $file) {
        if (isset($excluded[$file])) {
            $skippedFiles[] = $file;
            fwrite(STDOUT, "SKIP {$file} (included in production bundle)\n");
            continue;
        }

        $path = $databaseDir . '/' . $file;
        if (!is_file($path)) {
            throw new RuntimeException("Missing migration file: {$file}");
        }
        $sql = file_get_contents($path);
        if (!is_string($sql) || trim($sql) === '') {
            throw new RuntimeException("Empty migration file: {$file}");
        }

        fwrite(STDOUT, "APPLY {$file}\n");
        $pdo->exec($sql);

        $checksum = hash('sha256', $sql);
        $keys = mg_migration_keys_from_sql($sql, $file);
        foreach ($keys as $key) {
            $record->execute([$key, "Applied by {$file}", $checksum]);
            $appliedKeys[$key] = $file;
        }
        $appliedFiles[] = $file;
    }

    $expectedTargetKeys = [
        'stage_v1c_checkout_session_intent_authority',
        'stage_v1d_transfer_conversations',
        'stage_v1f_stripe_payments',
        'stage_v1_release_trigger_portability',
    ];
    $placeholders = implode(',', array_fill(0, count($expectedTargetKeys), '?'));
    $targetStmt = $pdo->prepare(
        "SELECT migration_key FROM schema_migrations WHERE migration_key IN ({$placeholders}) ORDER BY migration_key"
    );
    $targetStmt->execute($expectedTargetKeys);
    $unexpectedTargets = $targetStmt->fetchAll(PDO::FETCH_COLUMN);
    if ($unexpectedTargets !== []) {
        throw new RuntimeException(
            'Baseline unexpectedly contains production bundle migration markers: ' . implode(', ', $unexpectedTargets)
        );
    }

    $report = [
        'status' => 'passed',
        'database' => $databaseName,
        'started_at' => $startedAt,
        'completed_at' => gmdate('c'),
        'canonical_file_count' => count($orderedFiles),
        'applied_file_count' => count($appliedFiles),
        'applied_migration_key_count' => count($appliedKeys),
        'excluded_files' => array_values($skippedFiles),
        'excluded_migration_keys_absent' => true,
    ];

    $encoded = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
    if ($outputPath !== '') {
        $directory = dirname($outputPath);
        if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
            throw new RuntimeException('Unable to create production bundle evidence directory.');
        }
        if (file_put_contents($outputPath, $encoded) === false) {
            throw new RuntimeException('Unable to write production bundle baseline evidence.');
        }
    }
    fwrite(STDOUT, $encoded);
} finally {
    try {
        $release = $pdo->prepare('SELECT RELEASE_LOCK(?)');
        $release->execute([$lockName]);
    } catch (Throwable) {
        fwrite(STDERR, "Warning: unable to release production bundle baseline lock.\n");
    }
}
