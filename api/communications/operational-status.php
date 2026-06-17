<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap.php';
mg_require_method('POST');
$user = mg_require_permission('operational.alerts.manage');
$input = mg_input();
mg_require_csrf_for_write($input);
$id = trim((string) ($input['id'] ?? ''));
$action = trim((string) ($input['action'] ?? ''));
$allowed = ['acknowledge' => 'acknowledged', 'resolve' => 'resolved', 'dismiss' => 'dismissed'];
if (strlen($id) !== 36 || !preg_match('/^[a-f0-9-]{36}$/i', $id) || !isset($allowed[$action])) {
    mg_fail('Invalid operational status update.', 422);
}
$status = $allowed[$action];
$stmt = mg_db()->prepare("UPDATE operational_alerts SET status=?, acknowledged_by_user_id=CASE WHEN ?='acknowledged' THEN ? ELSE acknowledged_by_user_id END, acknowledged_at=CASE WHEN ?='acknowledged' THEN NOW() ELSE acknowledged_at END, resolved_at=CASE WHEN ?='resolved' THEN NOW() ELSE resolved_at END, updated_at=NOW() WHERE public_id=? AND user_id=?");
$stmt->execute([$status, $status, (int) $user['id'], $status, $status, strtolower($id), (int) $user['id']]);
if ($stmt->rowCount() < 1) {
    mg_fail('Operational alert not found.', 404);
}
mg_audit('operational.alert.updated', 'operational_alert', ['alert_id' => $id, 'status' => $status], (int) $user['id']);
mg_ok(['id' => $id, 'status' => $status], 'Operational alert updated.');
