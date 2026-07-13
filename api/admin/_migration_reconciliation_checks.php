<?php
declare(strict_types=1);

require_once __DIR__ . '/_migration_reconciliation_meta.php';

function mg_admin_migration_reconciliation_add_create_table_checks(PDO $pdo, array $create, array &$checks): void
{
    $table = (string)$create['table'];
    $exists = mg_admin_system_health_table_exists($pdo, $table);
    $checks[] = mg_admin_migration_reconciliation_check(
        'table:' . $table,
        'table',
        'Table ' . $table,
        $exists,
        $exists ? null : (string)$create['statement'],
        ['table' => $table]
    );

    foreach (mg_admin_migration_reconciliation_split_top_level((string)$create['body']) as $definition) {
        if (preg_match('/^`?([a-zA-Z0-9_]+)`?\s+(.+)$/is', $definition, $columnMatch) === 1
            && !in_array(strtoupper((string)$columnMatch[1]), ['PRIMARY','UNIQUE','KEY','INDEX','CONSTRAINT','CHECK','FOREIGN'], true)) {
            $column = (string)$columnMatch[1];
            $ready = $exists && mg_admin_migration_reconciliation_column($pdo, $table, $column) !== null;
            $repair = $exists && !$ready
                ? 'ALTER TABLE ' . mg_admin_migration_reconciliation_identifier($table)
                    . ' ADD COLUMN ' . mg_admin_migration_reconciliation_identifier($column) . ' ' . trim((string)$columnMatch[2]) . ';'
                : null;
            $checks[] = mg_admin_migration_reconciliation_check(
                'column:' . $table . '.' . $column,
                'column',
                'Column ' . $table . '.' . $column,
                $ready,
                $repair,
                ['table' => $table, 'column' => $column]
            );
            continue;
        }
        if (preg_match('/^(UNIQUE\s+KEY|KEY|INDEX)\s+`?([a-zA-Z0-9_]+)`?\s*(.+)$/is', $definition, $indexMatch) === 1) {
            $index = (string)$indexMatch[2];
            $ready = $exists && mg_admin_migration_reconciliation_index_exists($pdo, $table, $index);
            $repair = $exists && !$ready
                ? 'ALTER TABLE ' . mg_admin_migration_reconciliation_identifier($table) . ' ADD ' . trim($definition) . ';'
                : null;
            $checks[] = mg_admin_migration_reconciliation_check(
                'index:' . $table . '.' . $index,
                'index',
                'Index ' . $table . '.' . $index,
                $ready,
                $repair,
                ['table' => $table, 'index' => $index]
            );
            continue;
        }
        if (preg_match('/^CONSTRAINT\s+`?([a-zA-Z0-9_]+)`?\s+(.+)$/is', $definition, $constraintMatch) === 1) {
            $constraint = (string)$constraintMatch[1];
            $ready = $exists && mg_admin_migration_reconciliation_constraint_exists($pdo, $table, $constraint);
            $repair = $exists && !$ready
                ? 'ALTER TABLE ' . mg_admin_migration_reconciliation_identifier($table) . ' ADD ' . trim($definition) . ';'
                : null;
            $checks[] = mg_admin_migration_reconciliation_check(
                'constraint:' . $table . '.' . $constraint,
                'constraint',
                'Constraint ' . $table . '.' . $constraint,
                $ready,
                $repair,
                ['table' => $table, 'constraint' => $constraint]
            );
        }
    }
}

function mg_admin_migration_reconciliation_add_alter_checks(PDO $pdo, array $alter, array &$checks): void
{
    $table = (string)$alter['table'];
    $tableExists = mg_admin_system_health_table_exists($pdo, $table);
    foreach (mg_admin_migration_reconciliation_split_top_level((string)$alter['operations']) as $operation) {
        if (preg_match('/^ADD\s+(?:COLUMN\s+)?(?:IF\s+NOT\s+EXISTS\s+)?`?([a-zA-Z0-9_]+)`?\s+(.+)$/is', $operation, $columnMatch) === 1
            && !in_array(strtoupper((string)$columnMatch[1]), ['KEY','INDEX','UNIQUE','CONSTRAINT','PRIMARY'], true)) {
            $column = (string)$columnMatch[1];
            $ready = $tableExists && mg_admin_migration_reconciliation_column($pdo, $table, $column) !== null;
            $repair = $tableExists && !$ready
                ? 'ALTER TABLE ' . mg_admin_migration_reconciliation_identifier($table)
                    . ' ADD COLUMN ' . mg_admin_migration_reconciliation_identifier($column) . ' ' . trim((string)$columnMatch[2]) . ';'
                : null;
            $checks[] = mg_admin_migration_reconciliation_check(
                'column:' . $table . '.' . $column,
                'column',
                'Column ' . $table . '.' . $column,
                $ready,
                $repair,
                ['table' => $table, 'column' => $column]
            );
            continue;
        }
        if (preg_match('/^ADD\s+(UNIQUE\s+KEY|KEY|INDEX)\s+`?([a-zA-Z0-9_]+)`?\s*(.+)$/is', $operation, $indexMatch) === 1) {
            $index = (string)$indexMatch[2];
            $ready = $tableExists && mg_admin_migration_reconciliation_index_exists($pdo, $table, $index);
            $repair = $tableExists && !$ready
                ? 'ALTER TABLE ' . mg_admin_migration_reconciliation_identifier($table) . ' ' . trim($operation) . ';'
                : null;
            $checks[] = mg_admin_migration_reconciliation_check(
                'index:' . $table . '.' . $index,
                'index',
                'Index ' . $table . '.' . $index,
                $ready,
                $repair,
                ['table' => $table, 'index' => $index]
            );
            continue;
        }
        if (preg_match('/^ADD\s+CONSTRAINT\s+`?([a-zA-Z0-9_]+)`?\s+(.+)$/is', $operation, $constraintMatch) === 1) {
            $constraint = (string)$constraintMatch[1];
            $ready = $tableExists && mg_admin_migration_reconciliation_constraint_exists($pdo, $table, $constraint);
            $repair = $tableExists && !$ready
                ? 'ALTER TABLE ' . mg_admin_migration_reconciliation_identifier($table) . ' ' . trim($operation) . ';'
                : null;
            $checks[] = mg_admin_migration_reconciliation_check(
                'constraint:' . $table . '.' . $constraint,
                'constraint',
                'Constraint ' . $table . '.' . $constraint,
                $ready,
                $repair,
                ['table' => $table, 'constraint' => $constraint]
            );
            continue;
        }
        if (preg_match('/^MODIFY\s+(?:COLUMN\s+)?`?([a-zA-Z0-9_]+)`?\s+(.+)$/is', $operation, $modifyMatch) === 1) {
            $column = (string)$modifyMatch[1];
            $definition = trim((string)$modifyMatch[2]);
            $metadata = $tableExists ? mg_admin_migration_reconciliation_column($pdo, $table, $column) : null;
            $requiredEnum = mg_admin_migration_reconciliation_parse_enum_values($definition);
            if ($requiredEnum !== []) {
                $current = mg_admin_migration_reconciliation_enum_values($metadata);
                $missing = array_values(array_diff($requiredEnum, $current));
                $ready = $metadata !== null && $missing === [];
                $checks[] = mg_admin_migration_reconciliation_check(
                    'enum:' . $table . '.' . $column . ':' . hash('sha256', implode('|', $requiredEnum)),
                    'enum',
                    'ENUM values ' . $table . '.' . $column,
                    $ready,
                    $ready ? null : mg_admin_migration_reconciliation_enum_repair_sql($pdo, $table, $column, $requiredEnum),
                    ['table' => $table, 'column' => $column, 'required_values' => $requiredEnum, 'missing_values' => $missing]
                );
                continue;
            }
            if ($metadata !== null) {
                $targetNotNull = preg_match('/\bNOT\s+NULL\b/i', $definition) === 1;
                $targetNullable = !$targetNotNull && preg_match('/\bNULL\b/i', $definition) === 1;
                preg_match('/^([a-zA-Z]+(?:\s*\([^)]*\))?)/', $definition, $typeMatch);
                $targetType = strtolower(trim((string)($typeMatch[1] ?? '')));
                $typeReady = $targetType === '' || strtolower((string)$metadata['COLUMN_TYPE']) === $targetType;
                $nullReady = !$targetNotNull && !$targetNullable
                    ? true
                    : (($targetNotNull && (string)$metadata['IS_NULLABLE'] === 'NO') || ($targetNullable && (string)$metadata['IS_NULLABLE'] === 'YES'));
                $ready = $typeReady && $nullReady;
            } else {
                $ready = false;
            }
            $checks[] = mg_admin_migration_reconciliation_check(
                'definition:' . $table . '.' . $column,
                'column_definition',
                'Column definition ' . $table . '.' . $column,
                $ready,
                $metadata !== null && !$ready
                    ? 'ALTER TABLE ' . mg_admin_migration_reconciliation_identifier($table)
                        . ' MODIFY COLUMN ' . mg_admin_migration_reconciliation_identifier($column) . ' ' . $definition . ';'
                    : null,
                ['table' => $table, 'column' => $column]
            );
        }
    }
}

function mg_admin_migration_reconciliation_custom_checks(PDO $pdo, string $file): array
{
    if ($file !== 'delivery_operations_capacity_foundation_v1.sql') return [];
    $checks = [];
    $permissionsReady = mg_admin_system_health_table_exists($pdo, 'permissions');
    foreach (['delivery.operations.view', 'delivery.operations.manage'] as $slug) {
        $ready = false;
        if ($permissionsReady) {
            $stmt = $pdo->prepare('SELECT 1 FROM permissions WHERE slug=? LIMIT 1');
            $stmt->execute([$slug]);
            $ready = (bool)$stmt->fetchColumn();
        }
        $checks[] = mg_admin_migration_reconciliation_check(
            'row:permissions.slug:' . $slug,
            'row',
            'Permission ' . $slug,
            $ready,
            $permissionsReady && !$ready ? "INSERT IGNORE INTO permissions (slug,name,description,created_at) VALUES ("
                . $pdo->quote($slug) . ','
                . $pdo->quote($slug === 'delivery.operations.view' ? 'View delivery operations' : 'Manage delivery operations') . ','
                . $pdo->quote($slug === 'delivery.operations.view'
                    ? 'View delivery queue health, worker runs, channel status, and dead-letter records.'
                    : 'Retry, cancel, recover, and clear guarded delivery-worker pauses.') . ',NOW());' : null
        );
    }
    $rolePermissionTablesReady = $permissionsReady
        && mg_admin_system_health_table_exists($pdo, 'roles')
        && mg_admin_system_health_table_exists($pdo, 'role_permissions');
    $rolePermissionReady = false;
    if ($rolePermissionTablesReady) {
        $rolePermissionReady = (int)$pdo->query(
            "SELECT COUNT(*) FROM role_permissions rp
             JOIN roles r ON r.id=rp.role_id
             JOIN permissions p ON p.id=rp.permission_id
             WHERE r.slug IN ('admin','super_admin')
               AND p.slug IN ('delivery.operations.view','delivery.operations.manage')"
        )->fetchColumn() >= 2;
    }
    $checks[] = mg_admin_migration_reconciliation_check(
        'row:role_permissions.delivery_operations',
        'row',
        'Delivery operations role permissions',
        $rolePermissionReady,
        $rolePermissionTablesReady && !$rolePermissionReady
            ? "INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW()
FROM roles r
JOIN permissions p ON p.slug IN ('delivery.operations.view','delivery.operations.manage')
WHERE r.slug IN ('admin','super_admin');"
            : null
    );
    if (mg_admin_system_health_table_exists($pdo, 'mg_delivery_worker_state')) {
        $ready = (bool)$pdo->query('SELECT 1 FROM mg_delivery_worker_state WHERE id=1 LIMIT 1')->fetchColumn();
        $checks[] = mg_admin_migration_reconciliation_check(
            'row:mg_delivery_worker_state.id:1',
            'row',
            'Delivery worker state row',
            $ready,
            $ready ? null : 'INSERT IGNORE INTO mg_delivery_worker_state (id,paused) VALUES (1,0);'
        );
    }
    return $checks;
}
