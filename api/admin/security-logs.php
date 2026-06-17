<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

$user = mg_require_api_user();
if (!mg_api_user_has_permission($user, 'security.logs.view') && !mg_api_user_has_permission($user, 'admin.security_logs.view')) {
    mg_audit('permission_denied', 'security', ['permission' => 'security.logs.view'], (int) $user['id']);
    mg_security_log('warning', 'permission.denied', 'Permission denied.', ['permission' => 'security.logs.view'], (int) $user['id']);
    mg_fail('Permission denied.', 403);
}
$pdo = mg_db();

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if ($method !== 'GET') {
    mg_fail('Method not allowed.', 405);
}

$limit = max(1, min(200, (int) ($_GET['limit'] ?? 50)));
$severity = isset($_GET['severity']) ? trim((string) $_GET['severity']) : '';
$eventType = isset($_GET['event_type']) ? trim((string) $_GET['event_type']) : '';

$where = [];
$params = [];
if ($severity !== '') {
    $where[] = 'severity = ?';
    $params[] = $severity;
}
if ($eventType !== '') {
    $where[] = 'event_type = ?';
    $params[] = $eventType;
}

$sql = 'SELECT id, request_id, user_id, severity, event_type, message, context_json, ip_address, user_agent, created_at FROM security_logs';
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY id DESC LIMIT ' . $limit;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = array_map(static function (array $row): array {
    $decoded = json_decode((string) ($row['context_json'] ?? ''), true);
    $row['context'] = is_array($decoded) ? $decoded : [];
    unset($row['context_json']);
    return $row;
}, $stmt->fetchAll());

mg_audit('security_logs_viewed', 'security_logs', ['limit' => $limit], (int) $user['id']);
mg_ok(['security_logs' => $rows], 'Security logs loaded.');
