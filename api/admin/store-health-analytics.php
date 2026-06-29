<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/app.php';
require_once dirname(__DIR__, 2) . '/includes/admin-auth.php';

$user = mg_require_admin_page_key('admin.system_health');
$pdo = mg_db();

function mg_sha_admin_table_exists(PDO $pdo, string $table): bool
{
    try {
        $stmt = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1');
        $stmt->execute([$table]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable) {
        return false;
    }
}

function mg_sha_admin_scalar(PDO $pdo, string $sql, array $params = []): int
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    } catch (Throwable) {
        return 0;
    }
}

function mg_sha_admin_rows(PDO $pdo, string $sql, array $params = []): array
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable) {
        return [];
    }
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

if (!mg_sha_admin_table_exists($pdo, 'merchant_store_health_actions')) {
    echo json_encode(['ok' => false, 'error' => 'merchant_store_health_actions table is not installed', 'missing_table' => true], JSON_UNESCAPED_SLASHES);
    exit;
}

$total = mg_sha_admin_scalar($pdo, 'SELECT COUNT(*) FROM merchant_store_health_actions');
$started = mg_sha_admin_scalar($pdo, "SELECT COUNT(*) FROM merchant_store_health_actions WHERE status='started'");
$completed = mg_sha_admin_scalar($pdo, "SELECT COUNT(*) FROM merchant_store_health_actions WHERE status='completed'");
$snoozed = mg_sha_admin_scalar($pdo, "SELECT COUNT(*) FROM merchant_store_health_actions WHERE status='snoozed'");
$dismissed = mg_sha_admin_scalar($pdo, "SELECT COUNT(*) FROM merchant_store_health_actions WHERE status='dismissed'");
$activeMerchants = mg_sha_admin_scalar($pdo, 'SELECT COUNT(DISTINCT merchant_user_id) FROM merchant_store_health_actions');
$completionRate = $total > 0 ? round(($completed / $total) * 100, 1) : 0.0;
$startRate = $total > 0 ? round(($started / $total) * 100, 1) : 0.0;
$dismissRate = $total > 0 ? round(($dismissed / $total) * 100, 1) : 0.0;

$statusRows = mg_sha_admin_rows($pdo, 'SELECT status, COUNT(*) total FROM merchant_store_health_actions GROUP BY status ORDER BY total DESC');
$typeRows = mg_sha_admin_rows($pdo, 'SELECT action_type, COUNT(*) total, SUM(status="started") started, SUM(status="completed") completed, SUM(status="snoozed") snoozed, SUM(status="dismissed") dismissed FROM merchant_store_health_actions GROUP BY action_type ORDER BY total DESC LIMIT 12');
$merchantRows = mg_sha_admin_rows($pdo, 'SELECT a.merchant_user_id, COALESCE(NULLIF(u.display_name,""), NULLIF(u.full_name,""), u.email, CONCAT("Merchant #",a.merchant_user_id)) merchant_name, COUNT(*) total, SUM(a.status="started") started, SUM(a.status="completed") completed, SUM(a.status="snoozed") snoozed, SUM(a.status="dismissed") dismissed, MAX(a.updated_at) last_action_at FROM merchant_store_health_actions a LEFT JOIN users u ON u.id=a.merchant_user_id GROUP BY a.merchant_user_id, merchant_name ORDER BY total DESC, last_action_at DESC LIMIT 15');
$recentRows = mg_sha_admin_rows($pdo, 'SELECT a.public_id, a.merchant_user_id, COALESCE(NULLIF(u.display_name,""), NULLIF(u.full_name,""), u.email, CONCAT("Merchant #",a.merchant_user_id)) merchant_name, a.action_key, a.action_type, a.condition_key, a.title, a.priority, a.status, a.condition_count, a.updated_at FROM merchant_store_health_actions a LEFT JOIN users u ON u.id=a.merchant_user_id ORDER BY a.updated_at DESC LIMIT 20');
$dailyRows = mg_sha_admin_rows($pdo, 'SELECT DATE(updated_at) action_date, COUNT(*) total, SUM(status="started") started, SUM(status="completed") completed, SUM(status="snoozed") snoozed, SUM(status="dismissed") dismissed FROM merchant_store_health_actions WHERE updated_at >= DATE_SUB(CURRENT_DATE, INTERVAL 14 DAY) GROUP BY DATE(updated_at) ORDER BY action_date DESC');

$topTypes = array_map(static function (array $row): array {
    $total = (int)($row['total'] ?? 0);
    $completed = (int)($row['completed'] ?? 0);
    return [
        'action_type' => (string)($row['action_type'] ?? ''),
        'total' => $total,
        'started' => (int)($row['started'] ?? 0),
        'completed' => $completed,
        'snoozed' => (int)($row['snoozed'] ?? 0),
        'dismissed' => (int)($row['dismissed'] ?? 0),
        'completion_rate' => $total > 0 ? round(($completed / $total) * 100, 1) : 0.0,
    ];
}, $typeRows);

$merchants = array_map(static function (array $row): array {
    $total = (int)($row['total'] ?? 0);
    $completed = (int)($row['completed'] ?? 0);
    return [
        'merchant_user_id' => (int)($row['merchant_user_id'] ?? 0),
        'merchant_name' => (string)($row['merchant_name'] ?? ''),
        'total' => $total,
        'started' => (int)($row['started'] ?? 0),
        'completed' => $completed,
        'snoozed' => (int)($row['snoozed'] ?? 0),
        'dismissed' => (int)($row['dismissed'] ?? 0),
        'completion_rate' => $total > 0 ? round(($completed / $total) * 100, 1) : 0.0,
        'last_action_at' => $row['last_action_at'] ?? null,
    ];
}, $merchantRows);

$recent = array_map(static function (array $row): array {
    return [
        'id' => (string)($row['public_id'] ?? ''),
        'merchant_user_id' => (int)($row['merchant_user_id'] ?? 0),
        'merchant_name' => (string)($row['merchant_name'] ?? ''),
        'action_key' => (string)($row['action_key'] ?? ''),
        'action_type' => (string)($row['action_type'] ?? ''),
        'condition_key' => (string)($row['condition_key'] ?? ''),
        'title' => (string)($row['title'] ?? ''),
        'priority' => (string)($row['priority'] ?? ''),
        'status' => (string)($row['status'] ?? ''),
        'condition_count' => (int)($row['condition_count'] ?? 0),
        'updated_at' => $row['updated_at'] ?? null,
    ];
}, $recentRows);

$daily = array_map(static function (array $row): array {
    return [
        'date' => (string)($row['action_date'] ?? ''),
        'total' => (int)($row['total'] ?? 0),
        'started' => (int)($row['started'] ?? 0),
        'completed' => (int)($row['completed'] ?? 0),
        'snoozed' => (int)($row['snoozed'] ?? 0),
        'dismissed' => (int)($row['dismissed'] ?? 0),
    ];
}, $dailyRows);

echo json_encode([
    'ok' => true,
    'summary' => [
        'total' => $total,
        'started' => $started,
        'completed' => $completed,
        'snoozed' => $snoozed,
        'dismissed' => $dismissed,
        'active_merchants' => $activeMerchants,
        'completion_rate' => $completionRate,
        'start_rate' => $startRate,
        'dismiss_rate' => $dismissRate,
    ],
    'status' => $statusRows,
    'top_types' => $topTypes,
    'merchants' => $merchants,
    'recent' => $recent,
    'daily' => $daily,
], JSON_UNESCAPED_SLASHES);
