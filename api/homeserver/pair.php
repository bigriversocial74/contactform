<?php
declare(strict_types=1);

require_once __DIR__ . '/_homeserver.php';

mg_require_method('POST');
mg_homeserver_require_secure_transport();
$input = mg_homeserver_input();

$pairingCode = trim((string)($input['pairing_code'] ?? ''));
$installationId = strtolower(trim((string)($input['installation_id'] ?? '')));
$serverName = mg_homeserver_sanitize_server_name((string)($input['server_name'] ?? ''));
$version = trim(mb_substr((string)($input['version'] ?? ''), 0, 32));
$publicKeyEncoded = trim((string)($input['public_key'] ?? ''));
$publicKey = mg_homeserver_base64url_decode($publicKeyEncoded);

if (strlen($pairingCode) < 20 || strlen($pairingCode) > 80) mg_fail('Pairing code is invalid.', 422);
if (!mg_homeserver_is_uuid($installationId)) mg_fail('Installation identity is invalid.', 422);
if ($version === '') mg_fail('HomeServer version is required.', 422);
if (!is_string($publicKey) || strlen($publicKey) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) mg_fail('HomeServer public key is invalid.', 422);

$pdo = mg_db();
$token = mg_homeserver_device_token();
$tokenHash = mg_homeserver_token_hash($token);
$tokenLastFour = substr($token, -4);
$scopes = mg_homeserver_scopes();
$status = 201;

try {
    $pdo->beginTransaction();
    $codeStmt = $pdo->prepare('SELECT * FROM homeserver_pairing_codes WHERE code_hash=? AND consumed_at IS NULL AND expires_at>NOW() LIMIT 1 FOR UPDATE');
    $codeStmt->execute([hash('sha256', $pairingCode)]);
    $pairing = $codeStmt->fetch(PDO::FETCH_ASSOC);
    if (!$pairing) {
        $pdo->rollBack();
        mg_fail('Pairing code is expired, consumed, or invalid.', 409);
    }

    $ownerUserId = (int)$pairing['owner_user_id'];
    $deviceStmt = $pdo->prepare('SELECT * FROM homeserver_devices WHERE installation_id=? LIMIT 1 FOR UPDATE');
    $deviceStmt->execute([$installationId]);
    $device = $deviceStmt->fetch(PDO::FETCH_ASSOC);

    if ($device && (int)$device['owner_user_id'] !== $ownerUserId) {
        $pdo->rollBack();
        mg_fail('This HomeServer installation is already owned by another account.', 409);
    }

    if ($device) {
        $pdo->prepare("UPDATE homeserver_devices SET server_name=?,version=?,public_key_base64=?,token_hash=?,token_last_four=?,scopes_json=?,status='active',paired_at=NOW(),last_seen_at=NULL,revoked_at=NULL,updated_at=NOW() WHERE id=?")
            ->execute([$serverName, $version, $publicKeyEncoded, $tokenHash, $tokenLastFour, mg_homeserver_json($scopes), (int)$device['id']]);
        $deviceId = (int)$device['id'];
        $publicId = (string)$device['public_id'];
        $status = 200;
    } else {
        $publicId = mg_homeserver_public_uuid();
        $pdo->prepare("INSERT INTO homeserver_devices (public_id,owner_user_id,installation_id,server_name,version,public_key_base64,token_hash,token_last_four,scopes_json,status,paired_at,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,'active',NOW(),NOW(),NOW())")
            ->execute([$publicId, $ownerUserId, $installationId, $serverName, $version, $publicKeyEncoded, $tokenHash, $tokenLastFour, mg_homeserver_json($scopes)]);
        $deviceId = (int)$pdo->lastInsertId();
    }

    $pdo->prepare('UPDATE homeserver_pairing_codes SET consumed_at=NOW(),consumed_device_id=? WHERE id=? AND consumed_at IS NULL')
        ->execute([$deviceId, (int)$pairing['id']]);
    $pdo->commit();
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_fail_unexpected($error, 'homeserver.pair_failed', 'Unable to pair HomeServer.', 500, ['installation_id' => $installationId]);
}

mg_audit('homeserver.device_paired', 'homeserver_device', [
    'device_id' => $publicId,
    'installation_id' => $installationId,
    'server_name' => $serverName,
    'version' => $version,
    'scopes' => $scopes,
], $ownerUserId);

mg_ok([
    'device_id' => $publicId,
    'device_token' => $token,
    'scopes' => $scopes,
    'cloud_time_utc' => gmdate(DATE_ATOM),
    'signature_algorithm' => 'ed25519',
], $status === 201 ? 'HomeServer paired.' : 'HomeServer pairing renewed.', $status);
