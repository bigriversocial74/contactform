<?php
declare(strict_types=1);

require_once __DIR__ . '/_homeserver.php';

mg_require_method('GET');
$user = mg_require_api_user();
$pdo = mg_db();
$stmt = $pdo->prepare('SELECT * FROM homeserver_devices WHERE owner_user_id=? ORDER BY status ASC,updated_at DESC,id DESC');
$stmt->execute([(int)$user['id']]);
$devices = array_map('mg_homeserver_device_payload', $stmt->fetchAll(PDO::FETCH_ASSOC));
mg_ok(['devices' => $devices]);
