<?php
declare(strict_types=1);

function mg_hs_v1_pairing_fingerprint(array $input): string
{
    $fingerprint = [
        'sync_code_hash' => hash('sha256', (string)($input['sync_code'] ?? '')),
        'request_id' => (string)($input['request_id'] ?? ''),
        'installation_id' => (string)($input['installation_id'] ?? ''),
        'device_display_name' => (string)($input['device_display_name'] ?? ''),
        'homeserver_version' => (string)($input['homeserver_version'] ?? ''),
        'device_public_key' => (string)($input['device_public_key'] ?? ''),
        'requested_capabilities' => array_values((array)($input['requested_capabilities'] ?? [])),
        'merchant_id' => $input['merchant_id'] ?? null,
        'site_id' => $input['site_id'] ?? null,
        'replacement_id' => $input['replacement_id'] ?? null,
    ];
    return hash('sha256', json_encode($fingerprint, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
}

function mg_hs_v1_existing_exchange(PDO $pdo, string $requestId, string $fingerprint): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM homeserver_pairing_exchanges_v1 WHERE request_id=? LIMIT 1');
    $stmt->execute([$requestId]);
    $exchange = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$exchange) return null;
    if (!hash_equals((string)$exchange['request_fingerprint_hash'], $fingerprint)) {
        mg_hs_v1_fail('microgifter_pairing_interrupted', 'The pairing request identity was reused with different values.', 409);
    }
    if ((string)$exchange['state'] === 'completed'
        && !empty($exchange['response_ciphertext'])
        && !empty($exchange['response_nonce'])
        && strtotime((string)$exchange['response_expires_at'] . ' UTC') > time()) {
        return mg_hs_v1_decrypt_response((string)$exchange['response_ciphertext'], (string)$exchange['response_nonce']);
    }
    if ((string)$exchange['state'] === 'completed') {
        mg_hs_v1_fail('microgifter_pairing_interrupted', 'The pairing response recovery window has expired. Create a new Sync Code.', 409);
    }
    return null;
}

function mg_hs_v1_seed_device_credential(PDO $pdo, int $deviceId, string $token, int $version = 1): void
{
    $tokenHash = mg_homeserver_token_hash($token);
    $pdo->prepare("UPDATE homeserver_device_credentials SET state='revoked',revoked_at=UTC_TIMESTAMP() WHERE device_id=? AND state='current'")
        ->execute([$deviceId]);
    $stmt = $pdo->prepare("INSERT INTO homeserver_device_credentials (device_id,credential_version,token_hash,token_last_four,state,created_at) VALUES (?,?,?,?, 'current',UTC_TIMESTAMP())");
    $stmt->execute([$deviceId, $version, $tokenHash, substr($token, -4)]);
}

function mg_hs_v1_pairing_exchange(array $input): never
{
    mg_require_method('POST');
    mg_homeserver_require_secure_transport();
    $pdo = mg_db();
    mg_hs_v1_require_schema($pdo);
    if (!function_exists('sodium_crypto_sign_verify_detached')) {
        mg_hs_v1_fail('microgifter_signing_unavailable', 'HomeServer pairing verification is unavailable.', 503);
    }
    if (strtolower(trim((string)($input['provider_key'] ?? 'microgifter'))) !== 'microgifter') {
        mg_hs_v1_fail('microgifter_request_invalid', 'The HomeServer provider key is invalid.', 422);
    }
    $syncCode = mg_hs_v1_string($input['sync_code'] ?? '', 128, 'Microgifter Sync Code');
    if (strlen($syncCode) < 20 || preg_match('/^[A-Za-z0-9_.-]+$/', $syncCode) !== 1) {
        mg_hs_v1_fail('microgifter_sync_code_invalid', 'The Microgifter Sync Code is invalid.', 422);
    }
    $requestId = mg_hs_v1_uuid($input['request_id'] ?? mg_homeserver_header('X-MG-Request-ID'), 'request identity');
    $headerRequestId = trim(mg_homeserver_header('X-MG-Request-ID'));
    if ($headerRequestId !== '' && !hash_equals($requestId, strtolower($headerRequestId))) {
        mg_hs_v1_fail('microgifter_request_identity_mismatch', 'The pairing request identity does not match its header.', 409);
    }
    $installationId = mg_hs_v1_uuid($input['installation_id'] ?? '', 'installation identity');
    $deviceName = mg_homeserver_sanitize_server_name((string)($input['device_display_name'] ?? ''));
    $version = mg_hs_v1_string($input['homeserver_version'] ?? '', 32, 'HomeServer version');
    $publicKeyEncoded = mg_hs_v1_string($input['device_public_key'] ?? '', 100, 'device public key');
    $publicKey = mg_hs_v1_base64_decode($publicKeyEncoded);
    if (!is_string($publicKey) || strlen($publicKey) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
        mg_hs_v1_fail('microgifter_request_invalid', 'The HomeServer device public key is invalid.', 422);
    }
    $requestedCapabilities = array_values(array_unique(array_map('strval', (array)($input['requested_capabilities'] ?? []))));
    if (count($requestedCapabilities) > 64) {
        mg_hs_v1_fail('microgifter_request_invalid', 'Too many HomeServer capabilities were requested.', 422);
    }
    foreach ($requestedCapabilities as $capability) {
        if (strlen($capability) > 100 || preg_match('/^[a-z0-9.-]+$/', $capability) !== 1) {
            mg_hs_v1_fail('microgifter_request_invalid', 'A requested HomeServer capability is invalid.', 422);
        }
    }
    $merchantId = mg_hs_v1_optional_string($input['merchant_id'] ?? null, 120, 'merchant identity');
    $siteId = mg_hs_v1_optional_string($input['site_id'] ?? null, 120, 'site identity');
    $replacementId = isset($input['replacement_id']) && $input['replacement_id'] !== null
        ? mg_hs_v1_uuid($input['replacement_id'], 'replacement identity')
        : null;
    $fingerprint = mg_hs_v1_pairing_fingerprint([
        'sync_code' => $syncCode,
        'request_id' => $requestId,
        'installation_id' => $installationId,
        'device_display_name' => $deviceName,
        'homeserver_version' => $version,
        'device_public_key' => $publicKeyEncoded,
        'requested_capabilities' => $requestedCapabilities,
        'merchant_id' => $merchantId,
        'site_id' => $siteId,
        'replacement_id' => $replacementId,
    ]);
    try {
        if ($recovered = mg_hs_v1_existing_exchange($pdo, $requestId, $fingerprint)) {
            mg_hs_v1_ok($recovered, 'HomeServer pairing recovered.');
        }

        $pdo->beginTransaction();
        $codeStmt = $pdo->prepare('SELECT * FROM homeserver_pairing_codes WHERE code_hash=? LIMIT 1 FOR UPDATE');
        $codeStmt->execute([hash('sha256', $syncCode)]);
        $pairing = $codeStmt->fetch(PDO::FETCH_ASSOC);
        if (!$pairing) {
            $pdo->rollBack();
            mg_hs_v1_fail('microgifter_sync_code_invalid', 'The Microgifter Sync Code is invalid.', 409);
        }
        if (!empty($pairing['consumed_at'])) {
            $pdo->rollBack();
            mg_hs_v1_fail('microgifter_sync_code_used', 'The Microgifter Sync Code has already been used.', 409);
        }
        if (strtotime((string)$pairing['expires_at'] . ' UTC') <= time()) {
            $pdo->rollBack();
            mg_hs_v1_fail('microgifter_sync_code_expired', 'The Microgifter Sync Code has expired.', 409);
        }
        $ownerUserId = (int)$pairing['owner_user_id'];
        $owner = mg_hs_v1_user($pdo, $ownerUserId);
        $entitlement = mg_homeserver_entitlement_context($pdo, $owner);
        if (!mg_homeserver_entitlement_has($entitlement, 'homeserver.pair')) {
            $pdo->rollBack();
            mg_hs_v1_fail('microgifter_entitlement_missing', 'The owning Microgifter account is not entitled to pair HomeServer.', 403);
        }

        $replacement = null;
        $boundReplacementStmt = $pdo->prepare("SELECT * FROM homeserver_device_replacements_v1 WHERE pairing_code_id=? AND state IN ('pending','paired') LIMIT 1 FOR UPDATE");
        $boundReplacementStmt->execute([(int)$pairing['id']]);
        $boundReplacement = $boundReplacementStmt->fetch(PDO::FETCH_ASSOC);
        if ($boundReplacement && ($replacementId === null || !hash_equals((string)$boundReplacement['public_id'], $replacementId))) {
            $pdo->rollBack();
            mg_hs_v1_fail('microgifter_device_replacement_required', 'This Sync Code is reserved for a specific HomeServer replacement.', 409);
        }
        if ($replacementId !== null) {
            $replacementStmt = $pdo->prepare("SELECT * FROM homeserver_device_replacements_v1 WHERE public_id=? AND owner_user_id=? AND pairing_code_id=? AND state='pending' AND expires_at>UTC_TIMESTAMP() LIMIT 1 FOR UPDATE");
            $replacementStmt->execute([$replacementId, $ownerUserId, (int)$pairing['id']]);
            $replacement = $replacementStmt->fetch(PDO::FETCH_ASSOC);
            if (!$replacement) {
                $pdo->rollBack();
                mg_hs_v1_fail('microgifter_device_replacement_required', 'The replacement request is invalid or expired.', 409);
            }
        }

        $conflictStmt = $pdo->prepare('SELECT COUNT(*) FROM homeserver_devices WHERE installation_id=? AND owner_user_id<>?');
        $conflictStmt->execute([$installationId, $ownerUserId]);
        if ((int)$conflictStmt->fetchColumn() > 0) {
            $pdo->rollBack();
            mg_hs_v1_fail('microgifter_duplicate_device_identity', 'This HomeServer installation is already owned by another account.', 409);
        }
        $physicalStmt = $pdo->prepare("SELECT COUNT(*) FROM homeserver_devices WHERE installation_id=? AND owner_user_id=? AND status='active'");
        $physicalStmt->execute([$installationId, $ownerUserId]);
        $physicalInstallationExists = (int)$physicalStmt->fetchColumn() > 0;
        $activeDeviceCount = mg_hs_v1_physical_device_count($pdo, $ownerUserId);
        $deviceLimit = $entitlement['device_limit'] ?? 0;
        if ($replacement === null && !$physicalInstallationExists && $deviceLimit !== null && $activeDeviceCount >= (int)$deviceLimit) {
            $pdo->rollBack();
            mg_hs_v1_fail('microgifter_device_limit_reached', 'The Microgifter account has reached its physical HomeServer device allowance.', 409);
        }

        // A physical installation may host multiple isolated Microgifter connections.
        // Reuse only an unclaimed legacy device row; otherwise create a connection-scoped device identity.
        $deviceStmt = $pdo->prepare("SELECT d.* FROM homeserver_devices d LEFT JOIN homeserver_provider_connections c ON c.device_id=d.id WHERE d.installation_id=? AND d.owner_user_id=? AND c.id IS NULL ORDER BY (d.status='active') DESC,d.id ASC LIMIT 1 FOR UPDATE");
        $deviceStmt->execute([$installationId, $ownerUserId]);
        $device = $deviceStmt->fetch(PDO::FETCH_ASSOC);

        $deviceToken = mg_homeserver_device_token();
        $tokenHash = mg_homeserver_token_hash($deviceToken);
        $scopes = mg_homeserver_scopes();
        if ($device) {
            $pdo->prepare("UPDATE homeserver_devices SET server_name=?,version=?,public_key_base64=?,token_hash=?,token_last_four=?,scopes_json=?,status='active',paired_at=UTC_TIMESTAMP(),last_seen_at=NULL,revoked_at=NULL,updated_at=UTC_TIMESTAMP() WHERE id=?")
                ->execute([$deviceName, $version, $publicKeyEncoded, $tokenHash, substr($deviceToken, -4), mg_homeserver_json($scopes), (int)$device['id']]);
            $deviceId = (int)$device['id'];
            $devicePublicId = (string)$device['public_id'];
            $nextCredentialVersion = (int)$pdo->query("SELECT COALESCE(MAX(credential_version),0)+1 FROM homeserver_device_credentials WHERE device_id=" . $deviceId)->fetchColumn();
        } else {
            $devicePublicId = mg_homeserver_public_uuid();
            $pdo->prepare("INSERT INTO homeserver_devices (public_id,owner_user_id,installation_id,server_name,version,public_key_base64,token_hash,token_last_four,scopes_json,status,paired_at,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,'active',UTC_TIMESTAMP(),UTC_TIMESTAMP(),UTC_TIMESTAMP())")
                ->execute([$devicePublicId, $ownerUserId, $installationId, $deviceName, $version, $publicKeyEncoded, $tokenHash, substr($deviceToken, -4), mg_homeserver_json($scopes)]);
            $deviceId = (int)$pdo->lastInsertId();
            $nextCredentialVersion = 1;
        }
        mg_hs_v1_seed_device_credential($pdo, $deviceId, $deviceToken, max(1, $nextCredentialVersion));

        $connectionStmt = $pdo->prepare('SELECT * FROM homeserver_provider_connections WHERE device_id=? LIMIT 1 FOR UPDATE');
        $connectionStmt->execute([$deviceId]);
        $connection = $connectionStmt->fetch(PDO::FETCH_ASSOC);
        $decisions = mg_hs_v1_capability_decisions($entitlement, $requestedCapabilities);
        $providerConnectionPublicId = $connection ? (string)$connection['public_id'] : mg_homeserver_public_uuid();
        $provisionalConnection = [
            'id' => $connection['id'] ?? 0,
            'public_id' => $providerConnectionPublicId,
            'device_id' => $deviceId,
            'device_public_id' => $devicePublicId,
            'owner_user_id' => $ownerUserId,
            'requested_capabilities_json' => mg_homeserver_json($decisions['requested']),
        ];
        $scope = mg_hs_v1_scopes($pdo, $provisionalConnection, $merchantId, $siteId);
        $subscriptionState = mg_hs_v1_subscription_state($entitlement);
        $channels = ['stable'];
        if (!empty($entitlement['can_beta_updates'])) $channels[] = 'beta';
        if ($connection) {
            $pdo->prepare('UPDATE homeserver_provider_connections SET lifecycle_state=\'pairing_pending\',subscription_state=?,contract_version=\'v1\',requested_capabilities_json=?,granted_capabilities_json=?,denied_capabilities_json=?,merchant_scope_json=?,site_scope_json=?,update_eligible=?,update_channels_json=?,replacement_state=?,updated_at=UTC_TIMESTAMP() WHERE id=?')
                ->execute([$subscriptionState, mg_homeserver_json($decisions['requested']), mg_homeserver_json($decisions['granted']), mg_homeserver_json($decisions['denied']), mg_homeserver_json($scope['merchant_scope']), mg_homeserver_json($scope['site_scope']), !empty($entitlement['can_feature_updates']) ? 1 : 0, mg_homeserver_json($channels), $replacement ? 'pending' : 'none', (int)$connection['id']]);
            $connectionId = (int)$connection['id'];
        } else {
            $pdo->prepare('INSERT INTO homeserver_provider_connections
                (public_id,device_id,owner_user_id,contract_version,lifecycle_state,subscription_state,requested_capabilities_json,granted_capabilities_json,denied_capabilities_json,merchant_scope_json,site_scope_json,update_eligible,update_channels_json,replacement_state,created_at,updated_at)
                VALUES (?,?,?,\'v1\',\'pairing_pending\',?,?,?,?,?,?,?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())')
                ->execute([$providerConnectionPublicId, $deviceId, $ownerUserId, $subscriptionState, mg_homeserver_json($decisions['requested']), mg_homeserver_json($decisions['granted']), mg_homeserver_json($decisions['denied']), mg_homeserver_json($scope['merchant_scope']), mg_homeserver_json($scope['site_scope']), !empty($entitlement['can_feature_updates']) ? 1 : 0, mg_homeserver_json($channels), $replacement ? 'pending' : 'none']);
            $connectionId = (int)$pdo->lastInsertId();
        }
        $provisionalConnection['id'] = $connectionId;
        $provisionalConnection['requested_capabilities_json'] = mg_homeserver_json($decisions['requested']);

        $exchangePublicId = mg_homeserver_public_uuid();
        $pdo->prepare('INSERT INTO homeserver_pairing_exchanges_v1 (public_id,request_id,pairing_code_id,device_id,provider_connection_id,request_fingerprint_hash,state,created_at) VALUES (?,?,?,?,?,?,\'pending\',UTC_TIMESTAMP())')
            ->execute([$exchangePublicId, $requestId, (int)$pairing['id'], $deviceId, $connectionId, $fingerprint]);

        $lease = mg_hs_v1_issue_lease($pdo, $provisionalConnection, $entitlement, $merchantId, $siteId);
        $response = [
            'provider_connection_id' => $providerConnectionPublicId,
            'device_id' => $devicePublicId,
            'device_token' => $deviceToken,
            'owner_account_id' => mg_hs_v1_account_id($ownerUserId),
            'scopes' => $scopes,
            'entitlement_signing_key' => mg_hs_v1_signing_key_payload(),
            'entitlement_lease' => $lease,
        ];
        $encrypted = mg_hs_v1_encrypt_response($response);
        $pdo->prepare('UPDATE homeserver_pairing_exchanges_v1 SET response_ciphertext=?,response_nonce=?,response_expires_at=DATE_ADD(UTC_TIMESTAMP(),INTERVAL ? SECOND),state=\'completed\',completed_at=UTC_TIMESTAMP() WHERE request_id=?')
            ->execute([$encrypted['ciphertext'], $encrypted['nonce'], MG_HOMESERVER_PAIRING_RECOVERY_TTL_SECONDS, $requestId]);
        $pdo->prepare('UPDATE homeserver_pairing_codes SET consumed_at=UTC_TIMESTAMP(),consumed_device_id=? WHERE id=? AND consumed_at IS NULL')
            ->execute([$deviceId, (int)$pairing['id']]);
        if ($replacement) {
            $pdo->prepare("UPDATE homeserver_device_replacements_v1 SET new_device_id=?,new_provider_connection_id=?,state='paired',updated_at=UTC_TIMESTAMP() WHERE id=?")
                ->execute([$deviceId, $connectionId, (int)$replacement['id']]);
        }
        $pdo->commit();

        $connectionForReceipt = array_merge($provisionalConnection, ['id' => $connectionId]);
        mg_hs_v1_record_receipt($pdo, $connectionForReceipt, 'pairing.completed', 'success', $requestId, 'pairing_pending', mg_hs_v1_lifecycle_state($subscriptionState), null, [
            'contract_version' => 'v1',
            'replacement_id' => $replacementId,
        ]);
        mg_audit('homeserver.v1_pairing_completed', 'homeserver_device', [
            'device_id' => $devicePublicId,
            'provider_connection_id' => $providerConnectionPublicId,
            'request_id' => $requestId,
            'replacement_id' => $replacementId,
        ], $ownerUserId);
        mg_hs_v1_ok($response, 'HomeServer paired.', 201);
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        try {
            $pdo->prepare("UPDATE homeserver_pairing_exchanges_v1 SET state='failed',error_code=?,completed_at=UTC_TIMESTAMP() WHERE request_id=?")
                ->execute(['microgifter_pairing_failed', $requestId]);
        } catch (Throwable) {}
        mg_hs_v1_internal($error, 'microgifter_pairing_failed', 'Unable to complete HomeServer pairing.');
    }
}
