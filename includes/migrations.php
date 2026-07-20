<?php
declare(strict_types=1);

function mg_migration_manifest(): array
{
    static $manifest = null;
    if (is_array($manifest)) {
        return $manifest;
    }

    $loaded = require dirname(__DIR__) . '/config/migrations.php';
    if (!is_array($loaded) || !isset($loaded['ordered_files'], $loaded['coverage_markers'], $loaded['manual_only'])) {
        throw new RuntimeException('Invalid migration manifest.');
    }

    $manifest = $loaded;
    return $manifest;
}

function mg_migration_database_dir(): string
{
    return dirname(__DIR__) . '/database';
}

/**
 * Split a MySQL migration into executable statements while honoring mysql-client
 * DELIMITER directives. The delimiter directive itself is not sent to PDO.
 *
 * Migrations without DELIMITER directives continue to use the existing single
 * PDO::exec path through mg_migration_execute_sql().
 *
 * @return list<string>
 */
function mg_migration_sql_statements(string $sql): array
{
    $lines = preg_split('/\R/u', $sql);
    if (!is_array($lines)) {
        throw new RuntimeException('Could not read migration SQL lines.');
    }

    $delimiter = ';';
    $buffer = '';
    $statements = [];

    foreach ($lines as $lineNumber => $line) {
        $trimmedLine = trim($line);
        if (trim($buffer) === '' && preg_match('/^DELIMITER\s+(\S+)\s*$/i', $trimmedLine, $matches) === 1) {
            $delimiter = (string) ($matches[1] ?? '');
            if ($delimiter === '') {
                throw new RuntimeException('Empty DELIMITER directive at migration line ' . ($lineNumber + 1) . '.');
            }
            $buffer = '';
            continue;
        }

        $buffer .= $line . "\n";
        $candidate = rtrim($buffer);
        if ($candidate === '' || !str_ends_with($candidate, $delimiter)) {
            continue;
        }

        $statement = trim(substr($candidate, 0, -strlen($delimiter)));
        if ($statement !== '') {
            $statements[] = $statement;
        }
        $buffer = '';
    }

    if (trim($buffer) !== '') {
        throw new RuntimeException('Unterminated migration SQL statement for delimiter ' . $delimiter . '.');
    }

    return $statements;
}

/**
 * Execute one canonical migration. DELIMITER-aware files are executed one
 * parsed statement at a time because DELIMITER is a mysql-client command, not
 * server SQL and cannot be passed directly to PDO.
 */
function mg_migration_execute_sql(PDO $pdo, string $sql): void
{
    if (preg_match('/^\s*DELIMITER\s+/mi', $sql) !== 1) {
        $pdo->exec($sql);
        return;
    }

    $statements = mg_migration_sql_statements($sql);
    if ($statements === []) {
        throw new RuntimeException('Migration contains DELIMITER directives but no executable SQL statements.');
    }

    foreach ($statements as $statement) {
        $pdo->exec($statement);
    }
}

function mg_migration_keys_from_sql(string $sql, string $file): array
{
    $keys = [];
    preg_match_all(
        "/INSERT\s+(?:IGNORE\s+)?INTO\s+`?schema_migrations`?\s*\([^;]*?\)\s*VALUES\s*\(\s*'([^']+)'/is",
        $sql,
        $matches
    );

    foreach ($matches[1] ?? [] as $key) {
        $key = trim((string) $key);
        if ($key !== '') {
            $keys[$key] = true;
        }
    }

    if ($keys === []) {
        $keys[pathinfo($file, PATHINFO_FILENAME)] = true;
    }

    return array_keys($keys);
}

function mg_migration_applied_rows(PDO $pdo): array
{
    $rows = $pdo->query('SELECT migration_key, checksum FROM schema_migrations')->fetchAll(PDO::FETCH_ASSOC);
    $applied = [];
    foreach ($rows as $row) {
        $applied[(string) $row['migration_key']] = is_string($row['checksum'] ?? null) ? (string) $row['checksum'] : null;
    }
    return $applied;
}

function mg_migration_coverage_cutoff(array $appliedKeys, ?array $manifest = null): int
{
    $manifest ??= mg_migration_manifest();
    $ordered = array_values($manifest['ordered_files']);
    $positions = array_flip($ordered);
    $cutoff = -1;

    foreach ($manifest['coverage_markers'] as $marker => $throughFile) {
        if (!array_key_exists($marker, $appliedKeys)) {
            continue;
        }
        if (!array_key_exists($throughFile, $positions)) {
            throw new RuntimeException("Migration coverage marker {$marker} references an unknown file: {$throughFile}");
        }
        $cutoff = max($cutoff, (int) $positions[$throughFile]);
    }

    return $cutoff;
}

function mg_migration_status(PDO $pdo, ?string $databaseDir = null): array
{
    $manifest = mg_migration_manifest();
    $ordered = array_values($manifest['ordered_files']);
    $databaseDir ??= mg_migration_database_dir();
    $applied = mg_migration_applied_rows($pdo);
    $coverageCutoff = mg_migration_coverage_cutoff($applied, $manifest);

    $items = [];
    $missing = [];
    $checksumMismatches = [];

    foreach ($ordered as $index => $file) {
        $path = rtrim($databaseDir, '/') . '/' . $file;
        if (!is_file($path)) {
            $items[] = ['file' => $file, 'status' => 'missing_file', 'keys' => []];
            $missing[] = $file;
            continue;
        }

        $sql = file_get_contents($path);
        if (!is_string($sql) || trim($sql) === '') {
            $items[] = ['file' => $file, 'status' => 'empty_file', 'keys' => []];
            $missing[] = $file;
            continue;
        }

        $keys = mg_migration_keys_from_sql($sql, $file);
        $checksum = hash('sha256', $sql);
        $covered = $index <= $coverageCutoff;
        $directlyApplied = true;

        foreach ($keys as $key) {
            if (!array_key_exists($key, $applied)) {
                $directlyApplied = false;
                continue;
            }
            $storedChecksum = $applied[$key];
            if (is_string($storedChecksum) && $storedChecksum !== '' && !hash_equals($storedChecksum, $checksum)) {
                $checksumMismatches[] = ['file' => $file, 'key' => $key];
            }
        }

        $satisfied = $covered || $directlyApplied;
        if (!$satisfied) {
            $missing[] = $file;
        }

        $items[] = [
            'file' => $file,
            'keys' => $keys,
            'status' => $covered ? 'covered' : ($directlyApplied ? 'applied' : 'missing'),
            'checksum' => $checksum,
        ];
    }

    return [
        'ready' => $missing === [] && $checksumMismatches === [],
        'ordered_count' => count($ordered),
        'applied_key_count' => count($applied),
        'coverage_cutoff' => $coverageCutoff,
        'missing' => $missing,
        'checksum_mismatches' => $checksumMismatches,
        'items' => $items,
    ];
}
