<?php
declare(strict_types=1);

require_once __DIR__ . '/_homeserver.php';
require_once dirname(__DIR__, 2) . '/includes/homeserver-device-identity.php';
require_once dirname(__DIR__, 2) . '/includes/homeserver-entitlements.php';

mg_require_method('POST');
mg_homeserver_require_secure_transport();
if (!function_exists('sodium_crypto_sign_verify_detached')) mg_fail('HomeServer pairing verification is unavailable.', 503);
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
    $codeStmt = $pdo->prepare('SELECT * FROM homeserver_pairing_codes WHERE code_hash=? AND consumed_at IS NULL AND expires_at>UTC_TIMESTAMP() LIMIT 1 FOR UPDATE');
    $codeStmt->execute([hash('sha256', $pairingCode)]);
    $pairing = $codeStmt->fetch(PDO::FETCH_ASSOC);
    if (!$pairing) {
        $pdo->rollBack();
        mg_fail('Pairing code is expired, consumed, or invalid.', 409);
    }

    $ownerUserId = (int)$pairing['owner_user_id'];
    $ownerStmt = $pdo->prepare('SELECT * FROM users WHERE id=? LIMIT 1');
    $ownerStmt->execute([$ownerUserId]);
    $owner = $ownerStmt->fetch(PDO::FETCH_ASSOC);
    if (!$owner) {
        $pdo->rollBack();
        mg_fail('The owning Microgifter account was not found.', 404);
    }
    $entitlement = mg_homeserver_entitlement_context($pdo, $owner);
    if (!mg_homeserver_entitlement_has($entitlement, 'homeserver.pair')) {
        $pdo->rollBack();
        mg_fail('The owning Microgifter account is not entitled to pair HomeServer.', 403);
    }

    $conflictStmt = $pdo->prepare('SELECT COUNT(*) FROM homeserver_devices WHERE installation_id=? AND owner_user_id<>?');
    $conflictStmt->execute([$installationId, $ownerUserId]);
    if ((int)$conflictStmt->fetchColumn() > 0) {
        $pdo->rollBack();
        mg_fail('This HomeServer installation is already owned by another account.', 409);
    }
    $installationAlreadyActive = mg_homeserver_owner_has_installation($pdo, $ownerUserId, $installationId);
    $activeDeviceCount = mg_homeserver_active_device_count($pdo, $ownerUserId);
    $deviceLimit = $entitlement['device_limit'] ?? 0;
    if (!$installationAlreadyActive && $deviceLimit !== null && $activeDeviceCount >= (int)$deviceLimit) {
        $pdo->rollBack();
        mg_fail('This account has reached its physical HomeServer device allowance.', 409);
    }

    // Keep the legacy pairing route isolated from v1 provider/site connections.
    $providerTableStmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='homeserver_provider_connections'");
    $providerTableStmt->execute();
    if ((int)$providerTableStmt->fetchColumn() > 0) {
        $deviceStmt = $pdo->prepare("SELECT d.* FROM homeserver_devices d LEFT JOIN homeserver_provider_connections c ON c.device_id=d.id WHERE d.installation_id=? AND d.owner_user_id=? AND c.id IS NULL ORDER BY (d.status='active') DESC,d.id ASC LIMIT 1 FOR UPDATE");
    } else {
        $deviceStmt = $pdo->prepare('SELECT * FROM homeserver_devices WHERE installation_id=? AND owner_user_id=? LIMIT 1 FOR UPDATE');
    }
    $deviceStmt->execute([$installationId, $ownerUserId]);
    $device = $deviceStmt->fetch(PDO::FETCH_ASSOC);

    if ($device) {
        $pdo->prepare("UPDATE homeserver_devices SET server_name=?,version=?,public_key_base64=?,token_hash=?,token_last_four=?,scopes_json=?,status='active',paired_at=UTC_TIMESTAMP(),last_seen_at=NULL,revoked_at=NULL,updated_at=UTC_TIMESTAMP() WHERE id=?")
            ->execute([$serverName, $version, $publicKeyEncoded, $tokenHash, $tokenLastFour, mg_homeserver_json($scopes), (int)$device['id']]);
        $deviceId = (int)$device['id'];
        $publicId = (string)$device['public_id'];
        $status = 200;
    } else {
        $publicId = mg_homeserver_public_uuid();
        $pdo->prepare("INSERT INTO homeserver_devices (public_id,owner_user_id,installation_id,server_name,version,public_key_base64,token_hash,token_last_four,scopes_json,status,paired_at,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,'active',UTC_TIMESTAMP(),UTC_TIMESTAMP(),UTC_TIMESTAMP())")
            ->execute([$publicId, $ownerUserId, $installationId, $serverName, $version, $publicKeyEncoded, $tokenHash, $tokenLastFour, mg_homeserver_json($scopes)]);
        $deviceId = (int)$pdo->lastInsertId();
    }

    $pdo->prepare('UPDATE homeserver_pairing_codes SET consumed_at=UTC_TIMESTAMP(),consumed_device_id=? WHERE id=? AND consumed_at IS NULL')
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
