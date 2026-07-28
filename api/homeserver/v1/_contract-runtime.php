<?php
declare(strict_types=1);

function mg_hs_v1_refresh_entitlement(): never
{
    $context = mg_hs_v1_require_device('entitlement-lease.v1');
    $pdo = $context['pdo'];
    $connection = mg_hs_v1_connection_by_public($pdo, (string)$context['connection']['public_id']);
    try {
        $owner = mg_hs_v1_user($pdo, (int)$connection['owner_user_id']);
        $entitlement = mg_homeserver_entitlement_context($pdo, $owner);
        $lease = mg_hs_v1_issue_lease($pdo, $connection, $entitlement);
        mg_hs_v1_record_receipt($pdo, $connection, 'entitlement.lease_issued', 'success', $context['request_id'], null, mg_hs_v1_lifecycle_state(mg_hs_v1_subscription_state($entitlement)), null, [
            'lease_id' => $lease['payload']['lease_id'],
        ]);
        mg_hs_v1_ok(['entitlement_lease' => $lease], 'Entitlement lease refreshed.');
    } catch (Throwable $error) {
        mg_hs_v1_internal($error, 'microgifter_entitlement_refresh_failed', 'Unable to refresh the HomeServer entitlement lease.');
    }
}

function mg_hs_v1_heartbeat(): never
{
    $context = mg_hs_v1_require_device('device-heartbeat.v1');
    $pdo = $context['pdo'];
    $connection = mg_hs_v1_connection_by_public($pdo, (string)$context['connection']['public_id']);
    $payload = $context['payload'];
    try {
        $owner = mg_hs_v1_user($pdo, (int)$connection['owner_user_id']);
        $entitlement = mg_homeserver_entitlement_context($pdo, $owner);
        $subscriptionState = mg_hs_v1_subscription_state($entitlement);
        $state = (string)$connection['device_status'] === 'revoked' ? 'revoked' : mg_hs_v1_lifecycle_state($subscriptionState);
        $lease = $state === 'revoked' ? null : mg_hs_v1_issue_lease($pdo, $connection, $entitlement);
        $safeHeartbeat = [
            'connection_state' => mb_substr((string)($payload['connection_state'] ?? ''), 0, 40),
            'homeserver_version' => mb_substr((string)($payload['homeserver_version'] ?? ''), 0, 32),
            'cloud_contract_version' => mb_substr((string)($payload['cloud_contract_version'] ?? ''), 0, 16),
            'update_channel' => mb_substr((string)($payload['update_channel'] ?? ''), 0, 16),
            'health_category' => mb_substr((string)($payload['health_category'] ?? ''), 0, 40),
            'assigned_merchant_count' => max(0, (int)($payload['assigned_merchant_count'] ?? 0)),
            'assigned_site_count' => max(0, (int)($payload['assigned_site_count'] ?? 0)),
            'replacement_status' => mb_substr((string)($payload['replacement_status'] ?? ''), 0, 40),
        ];
        $pdo->prepare('UPDATE homeserver_provider_connections SET lifecycle_state=?,subscription_state=?,last_heartbeat_at=UTC_TIMESTAMP(),updated_at=UTC_TIMESTAMP() WHERE id=?')
            ->execute([$state, $subscriptionState, (int)$connection['id']]);
        mg_hs_v1_record_receipt($pdo, $connection, 'heartbeat.received', 'success', $context['request_id'], (string)$connection['lifecycle_state'], $state, null, $safeHeartbeat);
        mg_hs_v1_ok(['state' => $state, 'entitlement_lease' => $lease], 'Heartbeat accepted.');
    } catch (Throwable $error) {
        mg_hs_v1_internal($error, 'microgifter_heartbeat_failed', 'Unable to process the HomeServer heartbeat.');
    }
}

function mg_hs_v1_rotate_credentials(): never
{
    $context = mg_hs_v1_require_device('credential-rotation.v1');
    $pdo = $context['pdo'];
    $device = $context['device'];
    $connection = mg_hs_v1_connection_by_public($pdo, (string)$context['connection']['public_id']);
    $payload = $context['payload'];
    $rotationRequestId = mg_hs_v1_uuid($payload['rotation_request_id'] ?? '', 'credential rotation request identity');
    if ((string)($payload['device_id'] ?? '') !== (string)$device['public_id']) {
        mg_hs_v1_fail('microgifter_entitlement_device_mismatch', 'The credential rotation device identity does not match.', 409);
    }
    $requestHash = hash('sha256', mg_homeserver_json([
        'device_id' => (string)$device['public_id'],
        'rotation_request_id' => $rotationRequestId,
    ]));
    try {
        $existingStmt = $pdo->prepare('SELECT * FROM homeserver_credential_rotations WHERE request_id=? LIMIT 1');
        $existingStmt->execute([$rotationRequestId]);
        $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            if (!hash_equals((string)$existing['request_hash'], $requestHash)) {
                mg_hs_v1_fail('microgifter_credential_rotation_failed', 'The credential rotation request was reused with different values.', 409);
            }
            if (strtotime((string)$existing['response_expires_at'] . ' UTC') <= time()) {
                mg_hs_v1_fail('microgifter_credential_rotation_failed', 'The credential rotation recovery window expired.', 409);
            }
            mg_hs_v1_ok(mg_hs_v1_decrypt_response((string)$existing['response_ciphertext'], (string)$existing['response_nonce']), 'Credential rotation recovered.');
        }

        $newToken = mg_homeserver_device_token();
        $encrypted = mg_hs_v1_encrypt_response(['device_token' => $newToken]);
        $pdo->beginTransaction();
        $currentVersion = (int)$pdo->query('SELECT COALESCE(MAX(credential_version),0) FROM homeserver_device_credentials WHERE device_id=' . (int)$device['id'] . ' FOR UPDATE')->fetchColumn();
        if ($currentVersion < 1) {
            $pdo->prepare("INSERT INTO homeserver_device_credentials (device_id,credential_version,token_hash,token_last_four,state,created_at) VALUES (?,?,?,?, 'current',UTC_TIMESTAMP())")
                ->execute([(int)$device['id'], 1, (string)$device['token_hash'], (string)$device['token_last_four']]);
            $currentVersion = 1;
        }
        $pdo->prepare("UPDATE homeserver_device_credentials SET state='previous',valid_until=DATE_ADD(UTC_TIMESTAMP(),INTERVAL ? SECOND) WHERE device_id=? AND state='current'")
            ->execute([MG_HOMESERVER_PREVIOUS_CREDENTIAL_TTL_SECONDS, (int)$device['id']]);
        $newVersion = $currentVersion + 1;
        $newHash = mg_homeserver_token_hash($newToken);
        $pdo->prepare("INSERT INTO homeserver_device_credentials (device_id,credential_version,token_hash,token_last_four,state,created_at) VALUES (?,?,?,?, 'current',UTC_TIMESTAMP())")
            ->execute([(int)$device['id'], $newVersion, $newHash, substr($newToken, -4)]);
        $pdo->prepare('UPDATE homeserver_devices SET token_hash=?,token_last_four=?,updated_at=UTC_TIMESTAMP() WHERE id=?')
            ->execute([$newHash, substr($newToken, -4), (int)$device['id']]);
        $pdo->prepare('INSERT INTO homeserver_credential_rotations (public_id,request_id,device_id,request_hash,credential_version,response_ciphertext,response_nonce,response_expires_at,state,created_at) VALUES (?,?,?,?,?,?,?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL ? SECOND),\'completed\',UTC_TIMESTAMP())')
            ->execute([mg_homeserver_public_uuid(), $rotationRequestId, (int)$device['id'], $requestHash, $newVersion, $encrypted['ciphertext'], $encrypted['nonce'], MG_HOMESERVER_ROTATION_RECOVERY_TTL_SECONDS]);
        $pdo->prepare('UPDATE homeserver_provider_connections SET last_credential_rotation_at=UTC_TIMESTAMP(),updated_at=UTC_TIMESTAMP() WHERE id=?')
            ->execute([(int)$connection['id']]);
        $pdo->commit();
        mg_hs_v1_record_receipt($pdo, $connection, 'credential.rotated', 'success', $context['request_id'], null, null, null, ['credential_version' => $newVersion]);
        mg_hs_v1_ok(['device_token' => $newToken], 'HomeServer credential rotated.');
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        mg_hs_v1_internal($error, 'microgifter_credential_rotation_failed', 'Unable to rotate the HomeServer credential.');
    }
}

function mg_hs_v1_authorize_update(): never
{
    $context = mg_hs_v1_require_device('update-authorization.v1');
    $pdo = $context['pdo'];
    $connection = mg_hs_v1_connection_by_public($pdo, (string)$context['connection']['public_id']);
    $payload = $context['payload'];
    $updateId = mg_hs_v1_string($payload['update_id'] ?? '', 190, 'update identity');
    $version = mg_hs_v1_string($payload['version'] ?? '', 40, 'update version');
    $updateClass = strtolower(mg_hs_v1_string($payload['update_class'] ?? '', 20, 'update class'));
    $channel = strtolower(mg_hs_v1_string($payload['channel'] ?? '', 20, 'update channel'));
    if (!in_array($updateClass, ['bootstrap','security','maintenance','feature','preview','recovery'], true)) {
        mg_hs_v1_fail('microgifter_request_invalid', 'The update class is invalid.', 422);
    }
    if (!in_array($channel, ['stable','beta','preview'], true)) {
        mg_hs_v1_fail('microgifter_request_invalid', 'The update channel is invalid.', 422);
    }
    try {
        $existingStmt = $pdo->prepare('SELECT * FROM homeserver_update_authorizations_v1 WHERE request_id=? LIMIT 1');
        $existingStmt->execute([$context['request_id']]);
        $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            mg_hs_v1_ok([
                'authorization_id' => (string)$existing['public_id'],
                'decision' => (string)$existing['decision'],
                'reason_code' => $existing['reason_code'],
                'issued_at_utc' => gmdate(DATE_ATOM, strtotime((string)$existing['issued_at'] . ' UTC')),
                'expires_at_utc' => $existing['expires_at'] ? gmdate(DATE_ATOM, strtotime((string)$existing['expires_at'] . ' UTC')) : null,
            ], 'Update authorization recovered.');
        }

        $owner = mg_hs_v1_user($pdo, (int)$connection['owner_user_id']);
        $entitlement = mg_homeserver_entitlement_context($pdo, $owner);
        $subscriptionState = mg_hs_v1_subscription_state($entitlement);
        $allowedChannels = ['stable'];
        if (!empty($entitlement['can_beta_updates'])) $allowedChannels[] = 'beta';
        $decision = 'denied';
        $reason = 'microgifter_update_not_entitled';
        $expiresAt = null;
        if (in_array($updateClass, ['bootstrap', 'security', 'recovery'], true)) {
            $decision = 'not_required';
            $reason = 'independent_security_or_recovery_update';
        } elseif (!in_array($subscriptionState, ['active', 'grace'], true)) {
            $reason = 'subscription_not_entitled';
        } elseif (empty($entitlement['can_feature_updates'])) {
            $reason = 'feature_updates_not_entitled';
        } elseif (!in_array($channel, $allowedChannels, true)) {
            $reason = 'update_channel_not_entitled';
        } elseif ($updateClass === 'preview' && $channel !== 'preview') {
            $reason = 'preview_channel_required';
        } else {
            $decision = 'authorized';
            $reason = 'valid_entitlement';
            $expiresAt = time() + 900;
        }
        $authorizationId = mg_homeserver_public_uuid();
        $issuedAt = time();
        $stmt = $pdo->prepare('INSERT INTO homeserver_update_authorizations_v1
            (public_id,request_id,provider_connection_id,device_id,update_id,version,update_class,release_channel,decision,reason_code,issued_at,expires_at,created_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,FROM_UNIXTIME(?),FROM_UNIXTIME(?),UTC_TIMESTAMP())');
        $stmt->execute([
            $authorizationId, $context['request_id'], (int)$connection['id'], (int)$connection['device_id'],
            $updateId, $version, $updateClass, $channel, $decision, $reason, $issuedAt, $expiresAt,
        ]);
        $pdo->prepare('UPDATE homeserver_provider_connections SET last_update_authorization_at=UTC_TIMESTAMP(),updated_at=UTC_TIMESTAMP() WHERE id=?')
            ->execute([(int)$connection['id']]);
        mg_hs_v1_record_receipt($pdo, $connection, $decision === 'denied' ? 'update.denied' : 'update.authorized', $decision === 'denied' ? 'denied' : 'success', $context['request_id'], null, null, $reason, [
            'update_id' => $updateId,
            'version' => $version,
            'update_class' => $updateClass,
            'channel' => $channel,
        ]);
        mg_hs_v1_ok([
            'authorization_id' => $authorizationId,
            'decision' => $decision,
            'reason_code' => $reason,
            'issued_at_utc' => gmdate(DATE_ATOM, $issuedAt),
            'expires_at_utc' => $expiresAt ? gmdate(DATE_ATOM, $expiresAt) : null,
        ], 'Update authorization evaluated.');
    } catch (Throwable $error) {
        mg_hs_v1_internal($error, 'microgifter_update_authorization_failed', 'Unable to evaluate the HomeServer update authorization.');
    }
}

function mg_hs_v1_update_receipts(): never
{
    $context = mg_hs_v1_require_device('update-receipts.v1');
    $pdo = $context['pdo'];
    $connection = mg_hs_v1_connection_by_public($pdo, (string)$context['connection']['public_id']);
    $payload = $context['payload'];
    $receipts = $payload['receipts'] ?? null;
    if (!is_array($receipts)) $receipts = [$payload];
    if ($receipts === [] || count($receipts) > MG_HOMESERVER_V1_MAX_RECEIPTS) {
        mg_hs_v1_fail('microgifter_request_invalid', 'The update receipt batch is invalid.', 422);
    }
    $accepted = [];
    try {
        $pdo->beginTransaction();
        foreach ($receipts as $index => $receipt) {
            if (!is_array($receipt)) {
                $pdo->rollBack();
                mg_hs_v1_fail('microgifter_request_invalid', 'An update receipt is invalid.', 422, ['index' => $index]);
            }
            $receiptKey = mg_hs_v1_optional_string($receipt['receipt_id'] ?? $receipt['authorization_id'] ?? $receipt['update_id'] ?? null, 190, 'receipt identity')
                ?? hash('sha256', json_encode($receipt, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            $receiptType = mg_hs_v1_string($receipt['event_type'] ?? $receipt['receipt_type'] ?? $receipt['status'] ?? 'update.result', 100, 'receipt type');
            $updateId = mg_hs_v1_optional_string($receipt['update_id'] ?? null, 190, 'update identity');
            $version = mg_hs_v1_optional_string($receipt['version'] ?? null, 40, 'update version');
            $disposition = mg_hs_v1_optional_string($receipt['disposition'] ?? $receipt['result_state'] ?? $receipt['status'] ?? $receipt['result'] ?? null, 80, 'receipt disposition');
            $safe = [
                'receipt_id' => $receiptKey,
                'event_type' => $receiptType,
                'update_id' => $updateId,
                'version' => $version,
                'disposition' => $disposition,
                'occurred_at_utc' => mg_hs_v1_optional_string($receipt['occurred_at_utc'] ?? $receipt['created_at_utc'] ?? null, 40, 'receipt timestamp'),
                'reason_code' => mg_hs_v1_optional_string($receipt['reason_code'] ?? $receipt['failure_code'] ?? null, 120, 'receipt reason'),
            ];
            $payloadJson = mg_homeserver_json($safe);
            $publicId = mg_homeserver_public_uuid();
            try {
                $stmt = $pdo->prepare('INSERT INTO homeserver_update_receipts_v1
                    (public_id,request_id,provider_connection_id,device_id,receipt_key,receipt_type,update_id,version,disposition,payload_hash,payload_json,received_at)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,UTC_TIMESTAMP())');
                $stmt->execute([
                    $publicId, $context['request_id'], (int)$connection['id'], (int)$connection['device_id'],
                    $receiptKey, $receiptType, $updateId, $version, $disposition, hash('sha256', $payloadJson), $payloadJson,
                ]);
                $duplicate = false;
            } catch (PDOException $error) {
                if ((string)$error->getCode() !== '23000') throw $error;
                $duplicate = true;
            }
            $accepted[] = ['receipt_id' => $receiptKey, 'accepted' => true, 'duplicate' => $duplicate];
        }
        $pdo->commit();
        mg_hs_v1_record_receipt($pdo, $connection, 'update.receipts_received', 'success', $context['request_id'], null, null, null, ['receipt_count' => count($accepted)]);
        mg_hs_v1_ok(['accepted' => true, 'receipts' => $accepted], 'Update receipts accepted.');
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        mg_hs_v1_internal($error, 'microgifter_update_receipts_failed', 'Unable to process the HomeServer update receipts.');
    }
}
