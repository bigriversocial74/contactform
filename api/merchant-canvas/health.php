<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/store/_canvas_schema.php';

mg_require_method('GET');
$user = mg_require_api_user();
$pdo = mg_db();

if (!mg_user_has_merchant_access($user, $pdo)) {
    mg_fail('Merchant access required.', 403);
}

function mg_canvas_health_count(PDO $pdo, string $table, ?string $where = null, array $params = []): ?int
{
    if (!mg_store_canvas_table_exists($pdo, $table)) return null;
    if (preg_match('/^[A-Za-z0-9_]+$/', $table) !== 1) return null;
    try {
        $sql = 'SELECT COUNT(*) FROM `' . str_replace('`', '``', $table) . '`';
        if ($where !== null && trim($where) !== '') $sql .= ' WHERE ' . $where;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    } catch (Throwable) {
        return null;
    }
}

try {
    $merchantUserId = (int)$user['id'];
    mg_rate_limit('merchant_canvas.health', 'user:' . $merchantUserId, 120, 60);

    $coreTables = [
        'mg_store_sessions',
        'mg_store_session_events',
        'mg_customer_store_history',
        'mg_merchant_customer_crm',
        'mg_merchant_canvas_action_receipts',
    ];
    $deliveryTables = ['message_threads','messages','notifications','reward_templates','campaigns','wallet_items'];
    $optionalTables = ['mg_agent_messages'];
    $tableStatus = [];
    foreach (array_merge($coreTables, $deliveryTables, $optionalTables) as $table) {
        $tableStatus[$table] = ['exists' => mg_store_canvas_table_exists($pdo, $table)];
    }

    $missing = mg_store_canvas_missing_tables($pdo, array_merge($coreTables, $deliveryTables));
    $schemaReady = $missing === [];
    $stats = [
        'active_customers' => mg_canvas_health_count($pdo, 'mg_store_sessions', "merchant_user_id=? AND active_key IS NOT NULL AND status IN ('entered','active','idle') AND exited_at IS NULL AND last_active_at >= DATE_SUB(NOW(), INTERVAL " . MG_STORE_EXPIRE_MINUTES . " MINUTE)", [$merchantUserId]),
        'today_entries' => mg_canvas_health_count($pdo, 'mg_store_sessions', 'merchant_user_id=? AND entered_at >= CURDATE()', [$merchantUserId]),
        'today_events' => mg_canvas_health_count($pdo, 'mg_store_session_events', 'merchant_user_id=? AND created_at >= CURDATE()', [$merchantUserId]),
        'history_rows' => mg_canvas_health_count($pdo, 'mg_customer_store_history', 'merchant_user_id=?', [$merchantUserId]),
        'crm_records' => mg_canvas_health_count($pdo, 'mg_merchant_customer_crm', 'merchant_user_id=?', [$merchantUserId]),
        'protected_actions' => mg_canvas_health_count($pdo, 'mg_merchant_canvas_action_receipts', 'merchant_user_id=?', [$merchantUserId]),
    ];

    mg_ok([
        'status' => $schemaReady ? 'ready' : 'missing_schema',
        'schema' => [
            'ready' => $schemaReady,
            'missing' => $missing,
            'core_tables' => $coreTables,
            'delivery_tables' => $deliveryTables,
            'optional_tables' => $optionalTables,
            'tables' => $tableStatus,
        ],
        'stats' => $stats,
        'checked_at' => gmdate('c'),
    ]);
} catch (Throwable $error) {
    mg_security_log('error', 'merchant_canvas.health_failed', 'Merchant canvas health check failed.', ['exception_class'=>$error::class], (int)$user['id']);
    mg_fail('Unable to run Store Canvas diagnostics.', 500);
}
