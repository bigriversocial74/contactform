<?php
declare(strict_types=1);

/**
 * Robust Store Canvas schema detection helpers.
 *
 * Some production MySQL/PDO environments are unreliable with SHOW TABLES LIKE
 * through prepared statements. These helpers check the selected schema through
 * information_schema first, then fall back to SHOW TABLES and a zero-row table
 * probe so installed tables are not reported as missing incorrectly.
 */
require_once __DIR__ . '/_canvas.php';

function mg_store_canvas_table_exists(PDO $pdo, string $table): bool
{
    if (preg_match('/^[A-Za-z0-9_]+$/', $table) !== 1) {
        return false;
    }

    static $cache = [];
    $database = '';
    try {
        $database = (string)($pdo->query('SELECT DATABASE()')->fetchColumn() ?: '');
    } catch (Throwable) {
        $database = '';
    }

    $cacheKey = spl_object_id($pdo) . '|' . $database . '|' . strtolower($table);
    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    if ($database !== '') {
        try {
            $stmt = $pdo->prepare(
                'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? LIMIT 1'
            );
            $stmt->execute([$database, $table]);
            if ($stmt->fetchColumn()) {
                return $cache[$cacheKey] = true;
            }
        } catch (Throwable) {
            // Fall through to runtime probes.
        }
    }

    try {
        $quoted = $pdo->quote($table);
        if (is_string($quoted) && $quoted !== '') {
            $stmt = $pdo->query('SHOW TABLES LIKE ' . $quoted);
            if ($stmt && $stmt->fetchColumn()) {
                return $cache[$cacheKey] = true;
            }
        }
    } catch (Throwable) {
        // Fall through to direct probe.
    }

    try {
        $quotedTable = '`' . str_replace('`', '``', $table) . '`';
        $pdo->query('SELECT 1 FROM ' . $quotedTable . ' LIMIT 0');
        return $cache[$cacheKey] = true;
    } catch (Throwable) {
        return $cache[$cacheKey] = false;
    }
}

function mg_store_canvas_missing_tables(PDO $pdo, array $tables): array
{
    $missing = [];
    foreach ($tables as $table) {
        $table = trim((string)$table);
        if ($table === '' || !mg_store_canvas_table_exists($pdo, $table)) {
            $missing[] = $table;
        }
    }
    return $missing;
}

function mg_store_canvas_require_tables(PDO $pdo, array $tables, string $context = 'Store Canvas'): void
{
    $missing = mg_store_canvas_missing_tables($pdo, $tables);
    if ($missing !== []) {
        throw new RuntimeException($context . ' setup is incomplete. Missing: ' . implode(', ', $missing) . '. Run database/stage_20_agent_store_canvas.sql on the active database.');
    }
}
