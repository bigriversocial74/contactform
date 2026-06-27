<?php
declare(strict_types=1);

require_once __DIR__ . '/_claims.php';
require_once __DIR__ . '/_scanner_operations.php';

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$user = mg_require_permission('merchant.gifts.redeem');
$pdo = mg_db();
$merchantUserId = (int)$user['id'];
$workspace = mg_claim_workspace($pdo, $user);

if ($method === 'GET') {
    mg_rate_limit('merchant.scanner_devices.read', 'user:' . $merchantUserId, 120, 60);
    $stmt = $pdo->prepare('SELECT d.*, ml.name location_name FROM scanner_device_sessions d LEFT JOIN merchant_locations ml ON ml.id=d.location_id WHERE d.merchant_user_id=? AND (d.workspace_id IS NULL OR d.workspace_id=?) ORDER BY d.last_scan_at DESC, d.created_at DESC LIMIT 100');
    $stmt->execute([$merchantUserId, (int)$workspace['id']]);
    $devices = array_map(static function (array $row): array {
        return [
            'id' => (string)$row['public_id'],
            'label' => (string)($row['device_label'] ?? 'Merchant scanner'),
            'location_id' => (string)($row['location_public_id'] ?? ''),
            'location_name' => (string)($row['location_name'] ?? ''),
            'trusted_device' => (bool)$row['trusted_device'],
            'status' => (string)$row['status'],
            'first_seen_at' => $row['first_seen_at'] ?? null,
            'last_scan_at' => $row['last_scan_at'] ?? null,
        ];
    }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    mg_ok(['devices' => $devices], 'Scanner devices loaded.');
}

if ($method !== 'POST') mg_fail('Method not allowed.', 405);
$input = mg_input();
mg_require_csrf_for_write($input);
$deviceId = trim((string)($input['device_id'] ?? ''));
$action = strtolower(trim((string)($input['action'] ?? '')));
if (!preg_match('/^[0-9a-f-]{36}$/i', $deviceId)) mg_fail('Choose a scanner device.', 422);
if (!in_array($action, ['trust','untrust','disable','enable','rename'], true)) mg_fail('Invalid scanner device action.', 422);
$stmt = $pdo->prepare('SELECT * FROM scanner_device_sessions WHERE public_id=? AND merchant_user_id=? LIMIT 1');
$stmt->execute([$deviceId, $merchantUserId]);
$device = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$device) mg_fail('Scanner device not found.', 404);

if ($action === 'trust') $pdo->prepare('UPDATE scanner_device_sessions SET trusted_device=1,status="active",disabled_at=NULL,updated_at=NOW() WHERE id=?')->execute([(int)$device['id']]);
if ($action === 'untrust') $pdo->prepare('UPDATE scanner_device_sessions SET trusted_device=0,updated_at=NOW() WHERE id=?')->execute([(int)$device['id']]);
if ($action === 'disable') $pdo->prepare('UPDATE scanner_device_sessions SET status="disabled",disabled_at=NOW(),updated_at=NOW() WHERE id=?')->execute([(int)$device['id']]);
if ($action === 'enable') $pdo->prepare('UPDATE scanner_device_sessions SET status="active",disabled_at=NULL,updated_at=NOW() WHERE id=?')->execute([(int)$device['id']]);
if ($action === 'rename') {
    $label = mb_substr(trim((string)($input['device_label'] ?? 'Merchant scanner')), 0, 120);
    $pdo->prepare('UPDATE scanner_device_sessions SET device_label=?,updated_at=NOW() WHERE id=?')->execute([$label !== '' ? $label : 'Merchant scanner', (int)$device['id']]);
}
mg_ok(['device_id' => $deviceId, 'action' => $action], 'Scanner device updated.');
