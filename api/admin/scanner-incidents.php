<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap.php';
$user = mg_require_permission('admin.audit.view');
$pdo = mg_db();
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if ($method === 'GET') {
    $limit = max(1, min(100, (int)($_GET['limit'] ?? 50)));
    $stmt = $pdo->query('SELECT i.*, ml.name location_name, d.device_label FROM admin_scanner_incidents i LEFT JOIN merchant_locations ml ON ml.id=i.scanner_location_id LEFT JOIN scanner_device_sessions d ON d.id=i.scanner_device_session_id ORDER BY i.created_at DESC, i.id DESC LIMIT ' . $limit);
    mg_ok(['incidents' => $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : []], 'Scanner incidents loaded.');
}
if ($method !== 'POST') mg_fail('Method not allowed.', 405);
$input = mg_input();
mg_require_csrf_for_write($input);
$incidentId = trim((string)($input['incident_id'] ?? ''));
$status = strtolower(trim((string)($input['status'] ?? 'reviewing')));
if (!in_array($status, ['open','reviewing','dismissed','escalated','resolved'], true)) mg_fail('Invalid status.', 422);
if (!preg_match('/^[0-9a-f-]{36}$/i', $incidentId)) mg_fail('Choose an incident.', 422);
$stmt = $pdo->prepare('UPDATE admin_scanner_incidents SET status=?,reviewed_by_user_id=?,reviewed_at=NOW(),updated_at=NOW() WHERE public_id=?');
$stmt->execute([$status, (int)$user['id'], $incidentId]);
mg_ok(['incident_id' => $incidentId, 'status' => $status], 'Scanner incident updated.');
