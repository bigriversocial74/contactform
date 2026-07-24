<?php
declare(strict_types=1);

require_once __DIR__ . '/_homeserver.php';

mg_require_method('POST');
$user = mg_require_recent_auth();
$input = mg_homeserver_input();
mg_require_csrf_for_write($input);

$pdo = mg_db();
$ownerUserId = (int)$user['id'];
$code = mg_homeserver_pairing_code();
$expiresAt = gmdate('Y-m-d H:i:s', time() + MG_HOMESERVER_PAIRING_TTL_SECONDS);

try {
    $pdo->beginTransaction();
    $pdo->prepare('DELETE FROM homeserver_pairing_codes WHERE owner_user_id=? AND consumed_at IS NULL AND expires_at < UTC_TIMESTAMP()')
        ->execute([$ownerUserId]);
    $pdo->prepare('UPDATE homeserver_pairing_codes SET expires_at=UTC_TIMESTAMP() WHERE owner_user_id=? AND consumed_at IS NULL AND expires_at>UTC_TIMESTAMP()')
        ->execute([$ownerUserId]);
    $pdo->prepare('INSERT INTO homeserver_pairing_codes (public_id,owner_user_id,code_hash,expires_at,created_at) VALUES (?,?,?,?,UTC_TIMESTAMP())')
        ->execute([mg_homeserver_public_uuid(), $ownerUserId, hash('sha256', $code), $expiresAt]);
    $pdo->commit();
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_fail_unexpected($error, 'homeserver.pairing_code_failed', 'Unable to create a HomeServer pairing code.', 500, [], $ownerUserId);
}

mg_audit('homeserver.pairing_code_created', 'homeserver_device', ['expires_at' => $expiresAt], $ownerUserId);
mg_ok([
    'pairing_code' => $code,
    'expires_at_utc' => gmdate(DATE_ATOM, strtotime($expiresAt . ' UTC')),
    'expires_in_seconds' => MG_HOMESERVER_PAIRING_TTL_SECONDS,
], 'HomeServer pairing code created.', 201);
