<?php
declare(strict_types=1);

require_once __DIR__ . '/_stamps.php';

function mg_stamp_health_quote_identifier(string $identifier): string
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $identifier)) {
        throw new InvalidArgumentException('Invalid database identifier.');
    }
    return '`' . str_replace('`', '``', $identifier) . '`';
}

function mg_stamp_health_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name');
    $stmt->execute([':table_name' => $table]);
    return ((int)$stmt->fetchColumn()) > 0;
}

function mg_stamp_health_table_columns(PDO $pdo, string $table): array
{
    $colStmt = $pdo->query('SHOW COLUMNS FROM ' . mg_stamp_health_quote_identifier($table));
    if (!$colStmt) return [];
    return array_map(static fn(array $row): string => (string)$row['Field'], $colStmt->fetchAll(PDO::FETCH_ASSOC));
}

function mg_stamp_health_check_table(PDO $pdo, string $table, array $columns = []): array
{
    try {
        $exists = mg_stamp_health_table_exists($pdo, $table);
        $missingColumns = [];
        if ($exists && $columns) {
            $found = mg_stamp_health_table_columns($pdo, $table);
            $missingColumns = array_values(array_diff($columns, $found));
        }
        return ['name'=>$table,'ok'=>$exists && !$missingColumns,'exists'=>$exists,'missing_columns'=>$missingColumns];
    } catch (Throwable $error) {
        return ['name'=>$table,'ok'=>false,'exists'=>false,'missing_columns'=>$columns,'error'=>$error->getMessage()];
    }
}

function mg_stamp_health_check_file(string $relativePath): array
{
    $path = dirname(__DIR__, 2) . '/' . ltrim($relativePath, '/');
    return ['path'=>$relativePath,'ok'=>is_file($path),'exists'=>is_file($path)];
}

function mg_stamp_system_health(PDO $pdo): array
{
    $tables = [
        mg_stamp_health_check_table($pdo, 'stamp_debit_actions', ['action_key','stamp_value','status']),
        mg_stamp_health_check_table($pdo, 'account_stamp_balances', ['account_user_id','balance','included_monthly_stamps','purchased_stamps','used_stamps','voided_stamps','current_period_key']),
        mg_stamp_health_check_table($pdo, 'stamp_ledger_entries', ['public_id','account_user_id','entry_type','delta','balance_after','source_type','idempotency_key']),
        mg_stamp_health_check_table($pdo, 'stamp_bundles', ['public_id','bundle_key','label','stamps','price_cents','status']),
        mg_stamp_health_check_table($pdo, 'stamp_purchases', ['public_id','account_user_id','bundle_key','status','credited_ledger_entry_public_id']),
        mg_stamp_health_check_table($pdo, 'account_package_assignments', ['public_id','account_user_id','package_id','status']),
    ];
    $files = array_map('mg_stamp_health_check_file', [
        'database/stage_17_stamp_ledger.sql',
        'database/stage_17b_stamp_purchases.sql',
        'database/stage_17c_stamp_package_assignments.sql',
        'api/stamps/actions.php',
        'api/stamps/ledger.php',
        'api/stamps/debit.php',
        'api/stamps/credit.php',
        'api/stamps/bundles.php',
        'api/stamps/purchase.php',
        'api/stamps/purchase-complete.php',
        'api/stamps/monthly-renewals.php',
        'api/stamps/package-assignments.php',
        'api/stamps/adjustment.php',
        'api/stamps/void.php',
        'api/stamps/delivery-failure.php',
        'api/stamps/provider-delivery-webhook.php',
        'api/stamps/delivery-failure-report.php',
        'scripts/stamp_monthly_renewal.php',
    ]);
    $actions = mg_stamp_action_rows($pdo);
    $enabledActions = array_values(array_filter($actions, static fn(array $row): bool => !empty($row['enabled'])));
    $bundleCount = 0;
    try { $bundleCount = count(mg_stamp_bundle_rows($pdo)); } catch (Throwable $e) { $bundleCount = 0; }
    $checks = array_merge($tables, $files, [
        ['name'=>'enabled_stamp_actions','ok'=>count($enabledActions) >= 5,'count'=>count($enabledActions)],
        ['name'=>'active_stamp_bundles','ok'=>$bundleCount >= 1,'count'=>$bundleCount],
        ['name'=>'delivery_webhook_token_configured','ok'=>(bool)(getenv('MICROGIFTER_DELIVERY_WEBHOOK_TOKEN') ?: ''),'required_for_production'=>true],
    ]);
    $ok = !array_filter($checks, static fn(array $check): bool => empty($check['ok']));
    return [
        'ok' => $ok,
        'status' => $ok ? 'green' : 'needs_attention',
        'generated_at' => gmdate('c'),
        'tables' => $tables,
        'files' => $files,
        'counts' => ['enabled_stamp_actions'=>count($enabledActions),'active_stamp_bundles'=>$bundleCount],
        'checks' => $checks,
    ];
}
