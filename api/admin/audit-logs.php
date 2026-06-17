<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

mg_require_method('GET');
mg_require_permission('admin.audit.view');

$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 50;
$limit = max(1, min($limit, 100));

$pdo = mg_db();
$stmt = $pdo->prepare(
    'SELECT id, user_id, action, entity_type, metadata_json, ip_address, user_agent, created_at
     FROM audit_logs
     ORDER BY created_at DESC, id DESC
     LIMIT :limit'
);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->execute();

$logs = array_map(static function (array $row): array {
    $row['id'] = (int) $row['id'];
    $row['user_id'] = $row['user_id'] !== null ? (int) $row['user_id'] : null;
    $row['metadata'] = $row['metadata_json'] ? json_decode((string) $row['metadata_json'], true) : null;
    unset($row['metadata_json']);
    return $row;
}, $stmt->fetchAll());

mg_ok([
    'audit_logs' => $logs,
    'limit' => $limit,
], 'Audit logs loaded.');
