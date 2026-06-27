<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/store/_canvas_schema.php';

mg_require_method('GET');
$user = mg_require_api_user();
$pdo = mg_db();

$merchantAccess = mg_user_has_merchant_access($user, $pdo);
if (!$merchantAccess) {
    mg_fail('Merchant access required.', 403);
}

function mg_canvas_health_database_name(PDO $pdo): string
{
    try {
        return (string)($pdo->query('SELECT DATABASE()')->fetchColumn() ?: '');
    } catch (Throwable) {
        return '';
    }
}

function mg_canvas_health_count(PDO $pdo, string $table, ?string $where = null, array $params = []): ?int
{
    if (!mg_store_canvas_table_exists($pdo, $table)) {
        return null;
    }
    if (preg_match('/^[A-Za-z0-9_]+$/', $table) !== 1) {
        return null;
    }
    try {
        $sql = 'SELECT COUNT(*) FROM `' . str_replace('`', '``', $table) . '`';
        if ($where !== null && trim($where) !== '') {
            $sql .= ' WHERE ' . $where;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    } catch (Throwable) {
        return null;
    }
}

function mg_canvas_health_profile(PDO $pdo, int $userId): array
{
    $profile = [
        'user_id' => $userId,
        'display_name' => 'Merchant Agent',
        'avatar_url' => null,
        'profile_type' => 'merchant',
    ];
    try {
        $stmt = $pdo->prepare('SELECT display_name, avatar_url, profile_type FROM public_profiles WHERE user_id=? LIMIT 1');
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (is_array($row)) {
            $profile['display_name'] = trim((string)($row['display_name'] ?? '')) ?: $profile['display_name'];
            $profile['avatar_url'] = mg_store_avatar_url($row['avatar_url'] ?? null);
            $profile['profile_type'] = trim((string)($row['profile_type'] ?? '')) ?: $profile['profile_type'];
        }
    } catch (Throwable) {}
    return $profile;
}

try {
    mg_rate_limit('merchant_canvas.health', 'user:' . (int)$user['id'], 120, 60);

    $coreTables = ['mg_store_sessions','mg_store_session_events','mg_customer_store_history'];
    $optionalTables = ['mg_agent_messages','message_threads','messages','notifications','public_profiles','feed_posts'];
    $tableStatus = [];
    foreach (array_merge($coreTables, $optionalTables) as $table) {
        $tableStatus[$table] = [
            'exists' => mg_store_canvas_table_exists($pdo, $table),
            'rows' => mg_canvas_health_count($pdo, $table),
        ];
    }

    $merchantUserId = (int)$user['id'];
    $missing = mg_store_canvas_missing_tables($pdo, $coreTables);
    $schemaReady = $missing === [];

    $stats = [
        'active_customers' => mg_canvas_health_count($pdo, 'mg_store_sessions', "merchant_user_id=? AND active_key IS NOT NULL AND status IN ('entered','active','idle') AND exited_at IS NULL", [$merchantUserId]),
        'today_entries' => mg_canvas_health_count($pdo, 'mg_store_sessions', 'merchant_user_id=? AND entered_at >= CURDATE()', [$merchantUserId]),
        'today_events' => mg_canvas_health_count($pdo, 'mg_store_session_events', 'merchant_user_id=? AND created_at >= CURDATE()', [$merchantUserId]),
        'history_rows' => mg_canvas_health_count($pdo, 'mg_customer_store_history', 'merchant_user_id=?', [$merchantUserId]),
        'test_avatars' => mg_canvas_health_count($pdo, 'mg_store_sessions', "merchant_user_id=? AND active_key IS NOT NULL AND metadata_json LIKE '%merchant_canvas_test_seed%'", [$merchantUserId]),
        'audit_messages' => mg_canvas_health_count($pdo, 'mg_agent_messages', 'merchant_user_id=?', [$merchantUserId]),
    ];

    mg_ok([
        'status' => $schemaReady ? 'ready' : 'missing_schema',
        'database' => [
            'name' => mg_canvas_health_database_name($pdo),
            'driver' => (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME),
        ],
        'merchant' => [
            'user_id' => $merchantUserId,
            'access' => true,
            'profile' => mg_canvas_health_profile($pdo, $merchantUserId),
        ],
        'schema' => [
            'ready' => $schemaReady,
            'missing' => $missing,
            'core_tables' => $coreTables,
            'optional_tables' => $optionalTables,
            'tables' => $tableStatus,
        ],
        'stats' => $stats,
        'checked_at' => date('Y-m-d H:i:s'),
    ]);
} catch (Throwable $error) {
    mg_security_log('error', 'merchant_canvas.health_failed', 'Merchant canvas health check failed.', ['exception_class'=>$error::class,'exception'=>$error->getMessage()], (int)$user['id']);
    mg_fail('Unable to run Store Canvas diagnostics.', 500);
}
