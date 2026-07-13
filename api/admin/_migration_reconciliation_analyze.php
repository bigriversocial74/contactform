<?php
declare(strict_types=1);

require_once __DIR__ . '/_migration_reconciliation_checks.php';

function mg_admin_migration_reconciliation_dedupe_checks(array $checks): array
{
    $deduped = [];
    foreach ($checks as $check) {
        $id = (string)($check['id'] ?? '');
        if ($id === '') continue;
        if (!isset($deduped[$id])) {
            $deduped[$id] = $check;
            continue;
        }
        if (empty($deduped[$id]['ready']) && !empty($check['ready'])) $deduped[$id] = $check;
        elseif (empty($deduped[$id]['repair_sql']) && !empty($check['repair_sql'])) $deduped[$id]['repair_sql'] = $check['repair_sql'];
    }
    return array_values($deduped);
}

function mg_admin_migration_reconciliation_analyze_file(PDO $pdo, string $file): array
{
    $path = rtrim(mg_migration_database_dir(), '/') . '/' . $file;
    if (!is_file($path)) {
        return ['file' => $file, 'status' => 'missing_file', 'checks' => [], 'keys' => [], 'checksum' => null, 'recordable' => false, 'repair_sql' => null];
    }
    $sql = file_get_contents($path);
    if (!is_string($sql) || trim($sql) === '') {
        return ['file' => $file, 'status' => 'empty_file', 'checks' => [], 'keys' => [], 'checksum' => null, 'recordable' => false, 'repair_sql' => null];
    }

    $checks = [];
    foreach (mg_admin_migration_reconciliation_create_tables($sql) as $create) {
        mg_admin_migration_reconciliation_add_create_table_checks($pdo, $create, $checks);
    }
    foreach (mg_admin_migration_reconciliation_alter_statements($sql) as $alter) {
        mg_admin_migration_reconciliation_add_alter_checks($pdo, $alter, $checks);
    }
    $checks = mg_admin_migration_reconciliation_dedupe_checks(array_merge(
        $checks,
        mg_admin_migration_reconciliation_custom_checks($pdo, $file)
    ));

    $total = count($checks);
    $readyCount = count(array_filter($checks, static fn(array $check): bool => !empty($check['ready'])));
    $status = $total === 0 ? 'unsupported' : ($readyCount === $total ? 'installed' : ($readyCount === 0 ? 'missing' : 'partial'));
    $repairs = [];
    foreach ($checks as $check) {
        if (empty($check['ready']) && is_string($check['repair_sql'] ?? null) && trim((string)$check['repair_sql']) !== '') {
            $repairs[] = trim((string)$check['repair_sql']);
        }
    }
    $repairSql = $repairs === [] ? null : "-- Repair {$file}\n" . implode("\n", array_values(array_unique($repairs)));

    return [
        'file' => $file,
        'status' => $status,
        'checks' => $checks,
        'check_count' => $total,
        'ready_check_count' => $readyCount,
        'missing_check_count' => max(0, $total - $readyCount),
        'keys' => mg_migration_keys_from_sql($sql, $file),
        'checksum' => hash('sha256', $sql),
        'recordable' => $status === 'installed',
        'repair_sql' => $repairSql,
    ];
}
