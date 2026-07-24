<?php
declare(strict_types=1);

require_once __DIR__ . '/_homeserver.php';

mg_require_method('POST');
$user = mg_require_recent_auth();
$input = mg_homeserver_input();
mg_require_csrf_for_write($input);
$devicePublicId = strtolower(trim((string)($input['device_id'] ?? '')));
if (!mg_homeserver_is_uuid($devicePublicId)) mg_fail('HomeServer device identity is invalid.', 422);

$pdo = mg_db();
$stmt = $pdo->prepare("SELECT * FROM homeserver_devices WHERE public_id=? AND owner_user_id=? LIMIT 1");
$stmt->execute([$devicePublicId, (int)$user['id']]);
$device = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$device) mg_fail('HomeServer device was not found.', 404);
if ((string)$device['status'] === 'revoked') mg_ok(['device' => mg_homeserver_device_payload($device)], 'HomeServer device was already revoked.');

$invalidatedHash = hash('sha256', random_bytes(64));
$pdo->prepare("UPDATE homeserver_devices SET status='revoked',token_hash=?,revoked_at=NOW(),updated_at=NOW() WHERE id=?")
    ->execute([$invalidatedHash, (int)$device['id']]);
$device['status'] = 'revoked';
$device['revoked_at'] = gmdate('Y-m-d H:i:s');

mg_audit('homeserver.device_revoked', 'homeserver_device', ['device_id' => $devicePublicId], (int)$user['id']);
mg_ok(['device' => mg_homeserver_device_payload($device)], 'HomeServer device revoked.');
