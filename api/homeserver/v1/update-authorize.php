<?php
declare(strict_types=1);

require_once __DIR__ . '/_contract.php';
mg_hs_v1_require_route('/api/homeserver/v1/updates/authorize');
mg_require_method('POST');

$context = mg_hs_v1_require_device('device-heartbeat.v1');
$pdo = $context['pdo'];
$connection = mg_hs_v1_connection_by_public($pdo, (string)$context['connection']['public_id']);
$payload = $context['payload'];
$updateId = mg_hs_v1_string($payload['update_id'] ?? '', 190, 'update identity');
$version = mg_hs_v1_string($payload['version'] ?? '', 40, 'update version');
$updateClass = strtolower(mg_hs_v1_string($payload['update_class'] ?? '', 20, 'update class'));
$channel = strtolower(mg_hs_v1_string($payload['channel'] ?? '', 20, 'update channel'));

if (!in_array($updateClass, ['bootstrap', 'security', 'maintenance', 'feature', 'preview', 'recovery'], true)) {
    mg_hs_v1_fail('microgifter_request_invalid', 'The update class is invalid.', 422);
}
if (!in_array($channel, ['stable', 'beta', 'preview', 'security'], true)) {
    mg_hs_v1_fail('microgifter_request_invalid', 'The update channel is invalid.', 422);
}

$authorizationId = mg_homeserver_public_uuid();
$issuedAt = time();
$reason = 'vp3_software_authority';

try {
    $existingStmt = $pdo->prepare('SELECT * FROM homeserver_update_authorizations_v1 WHERE request_id=? LIMIT 1');
    $existingStmt->execute([$context['request_id']]);
    $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);
    if ($existing) {
        mg_hs_v1_ok([
            'authorization_id' => (string)$existing['public_id'],
            'decision' => 'not_required',
            'reason_code' => $reason,
            'issued_at_utc' => gmdate(DATE_ATOM, strtotime((string)$existing['issued_at'] . ' UTC')),
            'expires_at_utc' => null,
            'software_authority' => 'vp3',
        ], 'Microgifter does not authorize HomeServer software updates.');
    }

    $stmt = $pdo->prepare('INSERT INTO homeserver_update_authorizations_v1
        (public_id,request_id,provider_connection_id,device_id,update_id,version,update_class,release_channel,decision,reason_code,issued_at,expires_at,created_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,FROM_UNIXTIME(?),NULL,UTC_TIMESTAMP())');
    $stmt->execute([
        $authorizationId,
        $context['request_id'],
        (int)$connection['id'],
        (int)$connection['device_id'],
        $updateId,
        $version,
        $updateClass,
        $channel,
        'not_required',
        $reason,
        $issuedAt,
    ]);
    $pdo->prepare('UPDATE homeserver_provider_connections SET update_eligible=0,update_channels_json=\'[]\',last_update_authorization_at=UTC_TIMESTAMP(),updated_at=UTC_TIMESTAMP() WHERE id=?')
        ->execute([(int)$connection['id']]);
    mg_hs_v1_record_receipt($pdo, $connection, 'update.delegated_to_vp3', 'success', $context['request_id'], null, null, $reason, [
        'update_id' => $updateId,
        'version' => $version,
        'update_class' => $updateClass,
        'channel' => $channel,
        'software_authority' => 'vp3',
    ]);
    mg_hs_v1_ok([
        'authorization_id' => $authorizationId,
        'decision' => 'not_required',
        'reason_code' => $reason,
        'issued_at_utc' => gmdate(DATE_ATOM, $issuedAt),
        'expires_at_utc' => null,
        'software_authority' => 'vp3',
    ], 'Microgifter does not authorize HomeServer software updates.');
} catch (Throwable $error) {
    mg_hs_v1_internal($error, 'microgifter_update_authority_delegation_failed', 'Unable to record the VP3 update-authority boundary.');
}
