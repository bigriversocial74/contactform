<?php
declare(strict_types=1);

require_once __DIR__ . '/_migration_plan.php';

function mg_admin_migration_reconciliation_identifier(string $value): string
{
    if (preg_match('/^[a-zA-Z0-9_]{1,64}$/', $value) !== 1) {
        throw new InvalidArgumentException('Invalid migration reconciliation identifier.');
    }
    return '`' . $value . '`';
}

function mg_admin_migration_reconciliation_column(PDO $pdo, string $table, string $column): ?array
{
    $stmt = $pdo->prepare(
        'SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,CHARACTER_SET_NAME,COLLATION_NAME,EXTRA
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1'
    );
    $stmt->execute([$table, $column]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
}

function mg_admin_migration_reconciliation_index_exists(PDO $pdo, string $table, string $index): bool
{
    $stmt = $pdo->prepare(
        'SELECT 1 FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=? LIMIT 1'
    );
    $stmt->execute([$table, $index]);
    return (bool)$stmt->fetchColumn();
}

function mg_admin_migration_reconciliation_constraint_exists(PDO $pdo, string $table, string $constraint): bool
{
    $stmt = $pdo->prepare(
        'SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
         WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME=? AND CONSTRAINT_NAME=? LIMIT 1'
    );
    $stmt->execute([$table, $constraint]);
    return (bool)$stmt->fetchColumn();
}

function mg_admin_migration_reconciliation_enum_values(?array $column): array
{
    $type = is_array($column) ? (string)($column['COLUMN_TYPE'] ?? '') : '';
    if (preg_match('/^enum\((.*)\)$/is', $type, $match) !== 1) return [];
    preg_match_all("/'((?:''|[^'])*)'/", (string)$match[1], $values);
    return array_values(array_map(
        static fn(string $value): string => str_replace("''", "'", $value),
        $values[1] ?? []
    ));
}

function mg_admin_migration_reconciliation_split_top_level(string $value): array
{
    $parts = [];
    $buffer = '';
    $depth = 0;
    $quote = null;
    $length = strlen($value);
    for ($i = 0; $i < $length; $i++) {
        $char = $value[$i];
        if ($quote !== null) {
            $buffer .= $char;
            if ($char === $quote) {
                if ($quote === "'" && $i + 1 < $length && $value[$i + 1] === "'") {
                    $buffer .= $value[++$i];
                    continue;
                }
                if ($i === 0 || $value[$i - 1] !== '\\') $quote = null;
            }
            continue;
        }
        if ($char === "'" || $char === '"' || $char === '`') {
            $quote = $char;
            $buffer .= $char;
            continue;
        }
        if ($char === '(') $depth++;
        elseif ($char === ')') $depth = max(0, $depth - 1);
        if ($char === ',' && $depth === 0) {
            if (trim($buffer) !== '') $parts[] = trim($buffer);
            $buffer = '';
            continue;
        }
        $buffer .= $char;
    }
    if (trim($buffer) !== '') $parts[] = trim($buffer);
    return $parts;
}

function mg_admin_migration_reconciliation_mask_strings(string $sql): string
{
    $masked = $sql;
    $length = strlen($sql);
    $quote = null;
    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];
        if ($quote === null) {
            if ($char === "'") {
                $quote = "'";
                $masked[$i] = ' ';
            }
            continue;
        }
        $masked[$i] = $char === "\n" ? "\n" : ' ';
        if ($char === "'") {
            if ($i + 1 < $length && $sql[$i + 1] === "'") {
                $masked[++$i] = ' ';
                continue;
            }
            if ($i === 0 || $sql[$i - 1] !== '\\') $quote = null;
        }
    }
    return $masked;
}

function mg_admin_migration_reconciliation_create_tables(string $sql): array
{
    preg_match_all(
        '/CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS\s+`?([a-zA-Z0-9_]+)`?\s*\((.*?)\)\s*ENGINE\s*=\s*[^;]+;/is',
        $sql,
        $matches,
        PREG_SET_ORDER
    );
    $tables = [];
    foreach ($matches as $match) {
        $tables[] = [
            'table' => (string)$match[1],
            'body' => (string)$match[2],
            'statement' => trim((string)$match[0]),
        ];
    }
    return $tables;
}

function mg_admin_migration_reconciliation_alter_statements(string $sql): array
{
    $statements = [];
    $masked = mg_admin_migration_reconciliation_mask_strings($sql);
    preg_match_all(
        '/ALTER\s+TABLE\s+`?([a-zA-Z0-9_]+)`?\s+(.*?);/is',
        $masked,
        $matches,
        PREG_SET_ORDER | PREG_OFFSET_CAPTURE
    );
    foreach ($matches as $match) {
        $offset = (int)$match[0][1];
        $length = strlen((string)$match[0][0]);
        $original = substr($sql, $offset, $length);
        if (preg_match('/ALTER\s+TABLE\s+`?([a-zA-Z0-9_]+)`?\s+(.*?);/is', $original, $parsed) === 1) {
            $statements[] = ['table' => (string)$parsed[1], 'operations' => trim((string)$parsed[2])];
        }
    }

    preg_match_all("/'((?:''|[^'])*)'/s", $sql, $strings, PREG_SET_ORDER);
    foreach ($strings as $string) {
        $decoded = str_replace("''", "'", (string)$string[1]);
        if (preg_match('/^\s*ALTER\s+TABLE\s+`?([a-zA-Z0-9_]+)`?\s+(.*?)\s*$/is', $decoded, $parsed) === 1) {
            $statements[] = ['table' => (string)$parsed[1], 'operations' => trim((string)$parsed[2])];
        }
    }
    return $statements;
}

function mg_admin_migration_reconciliation_check(
    string $id,
    string $type,
    string $label,
    bool $ready,
    ?string $repairSql = null,
    array $context = []
): array {
    return array_merge([
        'id' => $id,
        'type' => $type,
        'label' => $label,
        'ready' => $ready,
        'repair_sql' => $ready ? null : $repairSql,
    ], $context);
}

function mg_admin_migration_reconciliation_enum_repair_sql(PDO $pdo, string $table, string $column, array $required): ?string
{
    $metadata = mg_admin_migration_reconciliation_column($pdo, $table, $column);
    if ($metadata === null) return null;
    $current = mg_admin_migration_reconciliation_enum_values($metadata);
    if ($current === []) return null;
    $values = $current;
    foreach ($required as $value) {
        if (!in_array($value, $values, true)) $values[] = $value;
    }
    $quoted = array_map(static fn(string $value): string => "'" . str_replace("'", "''", $value) . "'", $values);
    $definition = 'ENUM(' . implode(',', $quoted) . ')';
    if (!empty($metadata['CHARACTER_SET_NAME'])) $definition .= ' CHARACTER SET ' . $metadata['CHARACTER_SET_NAME'];
    if (!empty($metadata['COLLATION_NAME'])) $definition .= ' COLLATE ' . $metadata['COLLATION_NAME'];
    $definition .= ((string)($metadata['IS_NULLABLE'] ?? 'YES') === 'NO') ? ' NOT NULL' : ' NULL';
    $default = $metadata['COLUMN_DEFAULT'] ?? null;
    if ($default !== null) $definition .= ' DEFAULT ' . $pdo->quote((string)$default);
    return 'ALTER TABLE ' . mg_admin_migration_reconciliation_identifier($table)
        . ' MODIFY COLUMN ' . mg_admin_migration_reconciliation_identifier($column) . ' ' . $definition . ';';
}

function mg_admin_migration_reconciliation_parse_enum_values(string $definition): array
{
    if (preg_match('/\bENUM\s*\((.*?)\)/is', $definition, $match) !== 1) return [];
    preg_match_all("/'((?:''|[^'])*)'/", (string)$match[1], $values);
    return array_values(array_map(
        static fn(string $value): string => str_replace("''", "'", $value),
        $values[1] ?? []
    ));
}
