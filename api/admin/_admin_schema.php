<?php
declare(strict_types=1);

/**
 * Lightweight schema helpers for admin endpoints that need to tolerate partially
 * imported staged SQL migrations. These helpers are intentionally read-only.
 */
function mg_admin_schema_table_name(string $table): string
{
    return preg_replace('/[^A-Za-z0-9_]/', '', $table) ?? '';
}

function mg_admin_schema_columns(PDO $pdo, string $table): array
{
    static $cache = [];
    $table = mg_admin_schema_table_name($table);
    if ($table === '') {
        return [];
    }
    $key = spl_object_hash($pdo) . ':' . $table;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    try {
        $stmt = $pdo->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
        $stmt->execute([$table]);
        $columns = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $columns[(string)$row['COLUMN_NAME']] = true;
        }
        return $cache[$key] = $columns;
    } catch (Throwable $error) {
        return $cache[$key] = [];
    }
}

function mg_admin_schema_has_table(PDO $pdo, string $table): bool
{
    $table = mg_admin_schema_table_name($table);
    if ($table === '') {
        return false;
    }
    try {
        $stmt = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
        $stmt->execute([$table]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable $error) {
        return false;
    }
}

function mg_admin_schema_missing_columns(PDO $pdo, string $table, array $required): array
{
    $columns = mg_admin_schema_columns($pdo, $table);
    $missing = [];
    foreach ($required as $column) {
        if (empty($columns[$column])) {
            $missing[] = $column;
        }
    }
    return $missing;
}

function mg_admin_schema_has_columns(PDO $pdo, string $table, array $required): bool
{
    return mg_admin_schema_missing_columns($pdo, $table, $required) === [];
}

function mg_admin_schema_enum_values(PDO $pdo, string $table, string $column): array
{
    static $cache = [];
    $table = mg_admin_schema_table_name($table);
    $column = mg_admin_schema_table_name($column);
    if ($table === '' || $column === '') {
        return [];
    }
    $key = spl_object_hash($pdo) . ':' . $table . ':' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    try {
        $stmt = $pdo->prepare('SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1');
        $stmt->execute([$table, $column]);
        $type = (string)($stmt->fetchColumn() ?: '');
        if (stripos($type, 'enum(') !== 0) {
            return $cache[$key] = [];
        }
        $inner = substr($type, 5, -1);
        $values = str_getcsv($inner, ',', "'");
        $values = array_values(array_filter(array_map(static fn($value): string => stripcslashes((string)$value), $values), static fn($value): bool => $value !== ''));
        return $cache[$key] = $values;
    } catch (Throwable $error) {
        return $cache[$key] = [];
    }
}
