<?php
declare(strict_types=1);

function mg_hs_v1_connection_by_public(PDO $pdo, string $publicId, bool $forUpdate = false): array
{
    $sql = 'SELECT c.*,d.public_id AS device_public_id,d.installation_id,d.server_name,d.version,d.public_key_base64,d.status AS device_status,d.token_hash,d.token_last_four
            FROM homeserver_provider_connections c INNER JOIN homeserver_devices d ON d.id=c.device_id WHERE c.public_id=? LIMIT 1';
    if ($forUpdate) $sql .= ' FOR UPDATE';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$publicId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) mg_hs_v1_fail('microgifter_connection_not_found', 'The Microgifter HomeServer connection was not found.', 404);
    return $row;
}

function mg_hs_v1_record_receipt(PDO $pdo, ?array $connection, string $eventType, string $resultCategory, ?string $requestId = null, ?string $previousState = null, ?string $newState = null, ?string $errorCategory = null, array $metadata = []): void
{
    $stmt = $pdo->prepare('INSERT INTO homeserver_connection_receipts_v1
        (public_id,provider_connection_id,device_id,owner_user_id,request_id,event_type,previous_state,new_state,result_category,error_category,metadata_json,created_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,UTC_TIMESTAMP())');
    $stmt->execute([
        mg_homeserver_public_uuid(), $connection['id'] ?? null, $connection['device_id'] ?? null,
        $connection['owner_user_id'] ?? null, $requestId, mb_substr($eventType, 0, 100),
        $previousState, $newState, $resultCategory, $errorCategory,
        mg_homeserver_json($metadata),
    ]);
}

function mg_hs_v1_bearer_token(): string
{
    $authorization = mg_homeserver_authorization_header();
    if (preg_match('/^Bearer\s+([A-Za-z0-9_-]{32,200})$/i', $authorization, $match) !== 1) {
        mg_hs_v1_fail('microgifter_credentials_rejected', 'HomeServer device authentication is required.', 401);
    }
    return $match[1];
}

function mg_hs_v1_token_valid(PDO $pdo, array $device, string $token): bool
{
    $tokenHash = mg_homeserver_token_hash($token);
    if (hash_equals((string)$device['token_hash'], $tokenHash)) return true;
    try {
        $pdo->prepare("UPDATE homeserver_device_credentials SET state='revoked',revoked_at=UTC_TIMESTAMP() WHERE device_id=? AND state='previous' AND valid_until IS NOT NULL AND valid_until<UTC_TIMESTAMP()")
            ->execute([(int)$device['id']]);
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM homeserver_device_credentials WHERE device_id=? AND token_hash=? AND (state='current' OR (state='previous' AND valid_until>UTC_TIMESTAMP()))");
        $stmt->execute([(int)$device['id'], $tokenHash]);
        return (int)$stmt->fetchColumn() > 0;
    } catch (Throwable) {
        return false;
    }
}

function mg_hs_v1_require_device(string $requiredCapability): array
{
    mg_homeserver_require_secure_transport();
    if (!function_exists('sodium_crypto_sign_verify_detached')) {
        mg_hs_v1_fail('microgifter_signature_unavailable', 'HomeServer request verification is unavailable.', 503);
    }
    $pdo = mg_db();
    mg_hs_v1_require_schema($pdo);
    $devicePublicId = mg_hs_v1_uuid(mg_homeserver_header('X-MG-Homeserver-ID'), 'HomeServer device identity');
    $providerConnectionId = mg_hs_v1_uuid(mg_homeserver_header('X-MG-Provider-Connection-ID'), 'provider connection identity');
    if (mg_homeserver_header('X-MG-Contract-Version') !== MG_HOMESERVER_CLOUD_CONTRACT_VERSION) {
        mg_hs_v1_fail('microgifter_contract_version_unsupported', 'The HomeServer cloud contract version is unsupported.', 426);
    }
    $requestId = mg_hs_v1_uuid(mg_homeserver_header('X-MG-Request-ID'), 'request identity');
    $timestampRaw = mg_homeserver_header('X-MG-Timestamp');
    $nonce = mg_homeserver_header('X-MG-Nonce');
    $signatureEncoded = mg_homeserver_header('X-MG-Signature');
    if (!ctype_digit($timestampRaw) || abs(time() - (int)$timestampRaw) > MG_HOMESERVER_SIGNATURE_WINDOW_SECONDS) {
        mg_hs_v1_fail('microgifter_request_timestamp_invalid', 'HomeServer request timestamp is outside the allowed window.', 401);
    }
    if (preg_match('/^[A-Za-z0-9_-]{16,80}$/', $nonce) !== 1) {
        mg_hs_v1_fail('microgifter_request_nonce_invalid', 'HomeServer request nonce is invalid.', 401);
    }
    $token = mg_hs_v1_bearer_token();
    $stmt = $pdo->prepare('SELECT * FROM homeserver_devices WHERE public_id=? LIMIT 1');
    $stmt->execute([$devicePublicId]);
    $device = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$device || !mg_hs_v1_token_valid($pdo, $device, $token)) {
        mg_hs_v1_fail('microgifter_credentials_rejected', 'HomeServer device credentials are invalid.', 401);
    }
    if ((string)$device['status'] === 'revoked') {
        mg_hs_v1_fail('microgifter_connection_inactive', 'The HomeServer device has been revoked.', 403);
    }
    $connection = mg_hs_v1_connection_by_public($pdo, $providerConnectionId);
    if ((int)$connection['device_id'] !== (int)$device['id']) {
        mg_hs_v1_fail('microgifter_connection_device_mismatch', 'The provider connection does not belong to this HomeServer device.', 403);
    }
    $signature = mg_hs_v1_base64_decode($signatureEncoded);
    $publicKey = mg_hs_v1_base64_decode((string)$device['public_key_base64']);
    if (!is_string($signature) || strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES || !is_string($publicKey) || strlen($publicKey) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
        mg_hs_v1_fail('microgifter_signature_invalid', 'HomeServer request signature is invalid.', 401);
    }
    $canonical = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'POST')) . "\n"
        . mg_homeserver_request_path() . "\n" . $timestampRaw . "\n" . $nonce . "\n"
        . hash('sha256', mg_homeserver_raw_body());
    if (!sodium_crypto_sign_verify_detached($signature, $canonical, $publicKey)) {
        mg_hs_v1_fail('microgifter_signature_invalid', 'HomeServer request signature verification failed.', 401);
    }
    $body = mg_homeserver_input();
    if ((string)($body['request_id'] ?? '') !== $requestId) {
        mg_hs_v1_fail('microgifter_request_identity_mismatch', 'The request identity does not match the signed envelope.', 409);
    }
    if ((string)($body['device_id'] ?? '') !== $devicePublicId) {
        mg_hs_v1_fail('microgifter_entitlement_device_mismatch', 'The signed device identity does not match.', 409);
    }
    $localConnectionId = mg_hs_v1_uuid($body['connection_id'] ?? '', 'local connection identity');
    $payload = $body['payload'] ?? null;
    if (!is_array($payload)) mg_hs_v1_fail('microgifter_request_invalid', 'The signed provider payload is invalid.', 422);

    $granted = json_decode((string)$connection['granted_capabilities_json'], true);
    if ($requiredCapability !== '' && (!is_array($granted) || !in_array($requiredCapability, $granted, true))) {
        mg_hs_v1_fail('microgifter_capability_unsupported', 'The requested HomeServer capability is not granted.', 403);
    }
    try {
        $pdo->beginTransaction();
        $pdo->prepare('DELETE FROM homeserver_request_nonces WHERE expires_at<UTC_TIMESTAMP()')->execute();
        $pdo->prepare('INSERT INTO homeserver_request_nonces (device_id,nonce,requested_at,expires_at,created_at) VALUES (?,?,FROM_UNIXTIME(?),DATE_ADD(UTC_TIMESTAMP(),INTERVAL ? SECOND),UTC_TIMESTAMP())')
            ->execute([(int)$device['id'], $nonce, (int)$timestampRaw, MG_HOMESERVER_NONCE_TTL_SECONDS]);
        if (empty($connection['local_connection_id'])) {
            $pdo->prepare('UPDATE homeserver_provider_connections SET local_connection_id=?,updated_at=UTC_TIMESTAMP() WHERE id=? AND local_connection_id IS NULL')
                ->execute([$localConnectionId, (int)$connection['id']]);
            $connection['local_connection_id'] = $localConnectionId;
        } elseif (!hash_equals((string)$connection['local_connection_id'], $localConnectionId)) {
            $pdo->rollBack();
            mg_hs_v1_fail('microgifter_entitlement_connection_mismatch', 'The local connection identity does not match this provider connection.', 409);
        }
        $version = mb_substr(mg_homeserver_header('X-MG-Homeserver-Version'), 0, 32);
        $pdo->prepare('UPDATE homeserver_devices SET last_seen_at=UTC_TIMESTAMP(),version=COALESCE(NULLIF(?,\'\'),version),updated_at=UTC_TIMESTAMP() WHERE id=?')
            ->execute([$version, (int)$device['id']]);
        $pdo->commit();
    } catch (PDOException $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ((string)$error->getCode() === '23000') mg_hs_v1_fail('microgifter_request_replay', 'The HomeServer request replay was rejected.', 409);
        throw $error;
    }
    return [
        'pdo' => $pdo,
        'device' => $device,
        'connection' => $connection,
        'request_id' => $requestId,
        'local_connection_id' => $localConnectionId,
        'payload' => $payload,
    ];
}
