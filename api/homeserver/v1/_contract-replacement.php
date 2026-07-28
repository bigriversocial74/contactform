<?php
declare(strict_types=1);

function mg_hs_v1_replacement_start(): never
{
    $context = mg_hs_v1_require_device('device-replacement.v1');
    $pdo = $context['pdo'];
    $connection = mg_hs_v1_connection_by_public($pdo, (string)$context['connection']['public_id']);
    $payload = $context['payload'];
    if ((string)($payload['old_device_id'] ?? '') !== (string)$connection['device_public_id']) {
        mg_hs_v1_fail('microgifter_entitlement_device_mismatch', 'The replacement request does not match the current HomeServer device.', 409);
    }
    $newName = mg_homeserver_sanitize_server_name((string)($payload['new_device_display_name'] ?? 'Replacement HomeServer'));
    try {
        $existingStmt = $pdo->prepare('SELECT * FROM homeserver_device_replacements_v1 WHERE request_id=? LIMIT 1');
        $existingStmt->execute([$context['request_id']]);
        $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            if ((int)$existing['old_provider_connection_id'] !== (int)$connection['id']) {
                mg_hs_v1_fail('microgifter_device_replacement_required', 'The replacement request identity belongs to another connection.', 409);
            }
            if (!empty($existing['response_ciphertext']) && !empty($existing['response_nonce'])
                && strtotime((string)$existing['response_expires_at'] . ' UTC') > time()) {
                mg_hs_v1_ok(mg_hs_v1_decrypt_response((string)$existing['response_ciphertext'], (string)$existing['response_nonce']), 'HomeServer replacement recovered.');
            }
            mg_hs_v1_fail('microgifter_device_replacement_required', 'The replacement recovery window expired. Start a new replacement.', 409);
        }
        $owner = mg_hs_v1_user($pdo, (int)$connection['owner_user_id']);
        $entitlement = mg_homeserver_entitlement_context($pdo, $owner);
        if (!mg_homeserver_entitlement_has($entitlement, 'homeserver.pair')) {
            mg_hs_v1_fail('microgifter_entitlement_missing', 'The owning account is not entitled to replace a HomeServer device.', 403);
        }
        $syncCode = mg_homeserver_pairing_code();
        $replacementId = mg_homeserver_public_uuid();
        $expiresAt = time() + MG_HOMESERVER_PAIRING_TTL_SECONDS;
        $response = [
            'replacement_id' => $replacementId,
            'sync_code' => $syncCode,
            'expires_at_utc' => gmdate(DATE_ATOM, $expiresAt),
        ];
        $encrypted = mg_hs_v1_encrypt_response($response);
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE homeserver_device_replacements_v1 SET state='expired',updated_at=UTC_TIMESTAMP() WHERE old_provider_connection_id=? AND state='pending' AND expires_at<=UTC_TIMESTAMP()")
            ->execute([(int)$connection['id']]);
        $pairingPublicId = mg_homeserver_public_uuid();
        $pdo->prepare('INSERT INTO homeserver_pairing_codes (public_id,owner_user_id,code_hash,expires_at,created_at) VALUES (?,?,?,FROM_UNIXTIME(?),UTC_TIMESTAMP())')
            ->execute([$pairingPublicId, (int)$connection['owner_user_id'], hash('sha256', $syncCode), $expiresAt]);
        $pairingCodeId = (int)$pdo->lastInsertId();
        $pdo->prepare('INSERT INTO homeserver_device_replacements_v1
            (public_id,request_id,owner_user_id,old_device_id,old_provider_connection_id,pairing_code_id,requested_device_name,response_ciphertext,response_nonce,response_expires_at,state,expires_at,created_at,updated_at)
            VALUES (?,?,?,?,?,?,?,?,?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL ? SECOND),\'pending\',FROM_UNIXTIME(?),UTC_TIMESTAMP(),UTC_TIMESTAMP())')
            ->execute([$replacementId, $context['request_id'], (int)$connection['owner_user_id'], (int)$connection['device_id'], (int)$connection['id'], $pairingCodeId, $newName, $encrypted['ciphertext'], $encrypted['nonce'], MG_HOMESERVER_PAIRING_RECOVERY_TTL_SECONDS, $expiresAt]);
        $pdo->prepare("UPDATE homeserver_provider_connections SET lifecycle_state='replacing',replacement_state='pending',updated_at=UTC_TIMESTAMP() WHERE id=?")
            ->execute([(int)$connection['id']]);
        $pdo->commit();
        mg_hs_v1_record_receipt($pdo, $connection, 'device.replacement_initiated', 'success', $context['request_id'], (string)$connection['lifecycle_state'], 'replacing', null, ['replacement_id' => $replacementId]);
        mg_hs_v1_ok($response, 'HomeServer device replacement started.', 201);
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        mg_hs_v1_internal($error, 'microgifter_device_replacement_start_failed', 'Unable to start the HomeServer device replacement.');
    }
}

function mg_hs_v1_replacement_complete(): never
{
    $context = mg_hs_v1_require_device('device-replacement.v1');
    $pdo = $context['pdo'];
    $newConnection = mg_hs_v1_connection_by_public($pdo, (string)$context['connection']['public_id']);
    $payload = $context['payload'];
    $replacementId = mg_hs_v1_uuid($payload['replacement_id'] ?? '', 'replacement identity');
    $oldDevicePublicId = mg_hs_v1_uuid($payload['old_device_id'] ?? '', 'old device identity');
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT r.*,d.public_id AS old_device_public_id FROM homeserver_device_replacements_v1 r INNER JOIN homeserver_devices d ON d.id=r.old_device_id WHERE r.public_id=? AND r.new_provider_connection_id=? AND r.new_device_id=? AND r.state IN ('paired','completed') LIMIT 1 FOR UPDATE");
        $stmt->execute([$replacementId, (int)$newConnection['id'], (int)$newConnection['device_id']]);
        $replacement = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$replacement || !hash_equals((string)$replacement['old_device_public_id'], $oldDevicePublicId)) {
            $pdo->rollBack();
            mg_hs_v1_fail('microgifter_device_replacement_required', 'The HomeServer replacement is not ready to complete.', 409);
        }
        if ((string)$replacement['state'] === 'completed') {
            $pdo->commit();
            mg_hs_v1_ok(['completed' => true], 'HomeServer device replacement was already completed.');
        }
        $invalidatedHash = hash('sha256', random_bytes(64));
        $pdo->prepare("UPDATE homeserver_devices SET status='revoked',token_hash=?,revoked_at=UTC_TIMESTAMP(),updated_at=UTC_TIMESTAMP() WHERE id=?")
            ->execute([$invalidatedHash, (int)$replacement['old_device_id']]);
        $pdo->prepare("UPDATE homeserver_device_credentials SET state='revoked',revoked_at=UTC_TIMESTAMP() WHERE device_id=?")
            ->execute([(int)$replacement['old_device_id']]);
        $pdo->prepare("UPDATE homeserver_provider_connections SET lifecycle_state='revoked',replacement_state='completed',updated_at=UTC_TIMESTAMP() WHERE id=?")
            ->execute([(int)$replacement['old_provider_connection_id']]);
        $pdo->prepare("UPDATE homeserver_provider_connections SET lifecycle_state='active',replacement_state='completed',updated_at=UTC_TIMESTAMP() WHERE id=?")
            ->execute([(int)$newConnection['id']]);
        $pdo->prepare("UPDATE homeserver_device_replacements_v1 SET state='completed',completed_at=UTC_TIMESTAMP(),updated_at=UTC_TIMESTAMP() WHERE id=?")
            ->execute([(int)$replacement['id']]);
        $pdo->commit();
        mg_hs_v1_record_receipt($pdo, $newConnection, 'device.replacement_completed', 'success', $context['request_id'], 'replacing', 'active', null, ['replacement_id' => $replacementId]);
        mg_hs_v1_ok(['completed' => true], 'HomeServer device replacement completed.');
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        mg_hs_v1_internal($error, 'microgifter_device_replacement_complete_failed', 'Unable to complete the HomeServer device replacement.');
    }
}
