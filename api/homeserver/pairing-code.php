<?php
declare(strict_types=1);

require_once __DIR__ . '/_homeserver.php';
require_once dirname(__DIR__, 2) . '/includes/homeserver-device-identity.php';
require_once dirname(__DIR__, 2) . '/includes/homeserver-entitlements.php';

mg_require_method('POST');
$user = mg_require_api_user();
$input = mg_homeserver_input();
mg_require_csrf_for_write($input);

$pdo = mg_db();
$ownerUserId = (int)$user['id'];
$entitlement = mg_homeserver_require_capability(
    $pdo,
    $user,
    'homeserver.pair',
    'Creating a HomeServer Sync Code requires an active paid or complimentary Microgifter package.'
);
$activeDeviceCount = mg_homeserver_active_device_count($pdo, $ownerUserId);
$deviceLimit = $entitlement['device_limit'] ?? 0;
$newPhysicalDeviceSlotAvailable = $deviceLimit === null || $activeDeviceCount < (int)$deviceLimit;

// The installation identity is not known until exchange. A Sync Code may therefore
// still be created at the physical-device limit for an additional isolated site/provider
// connection on an already authorized HomeServer. The exchange endpoint enforces the
// limit before accepting a previously unseen installation identity.
$code = mg_homeserver_pairing_code();
$expiresAt = gmdate('Y-m-d H:i:s', time() + MG_HOMESERVER_PAIRING_TTL_SECONDS);

try {
    $pdo->beginTransaction();
    $replacementTableStmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='homeserver_device_replacements_v1'");
    $replacementTableStmt->execute();
    $hasReplacementTable = (int)$replacementTableStmt->fetchColumn() > 0;
    if ($hasReplacementTable) {
        $pdo->prepare("DELETE pc FROM homeserver_pairing_codes pc LEFT JOIN homeserver_device_replacements_v1 r ON r.pairing_code_id=pc.id AND r.state IN ('pending','paired') WHERE pc.owner_user_id=? AND pc.consumed_at IS NULL AND pc.expires_at<UTC_TIMESTAMP() AND r.id IS NULL")
            ->execute([$ownerUserId]);
        $pdo->prepare("UPDATE homeserver_pairing_codes pc LEFT JOIN homeserver_device_replacements_v1 r ON r.pairing_code_id=pc.id AND r.state IN ('pending','paired') SET pc.expires_at=UTC_TIMESTAMP() WHERE pc.owner_user_id=? AND pc.consumed_at IS NULL AND pc.expires_at>UTC_TIMESTAMP() AND r.id IS NULL")
            ->execute([$ownerUserId]);
    } else {
        $pdo->prepare('DELETE FROM homeserver_pairing_codes WHERE owner_user_id=? AND consumed_at IS NULL AND expires_at < UTC_TIMESTAMP()')
            ->execute([$ownerUserId]);
        $pdo->prepare('UPDATE homeserver_pairing_codes SET expires_at=UTC_TIMESTAMP() WHERE owner_user_id=? AND consumed_at IS NULL AND expires_at>UTC_TIMESTAMP()')
            ->execute([$ownerUserId]);
    }
    $pdo->prepare('INSERT INTO homeserver_pairing_codes (public_id,owner_user_id,code_hash,expires_at,created_at) VALUES (?,?,?,?,UTC_TIMESTAMP())')
        ->execute([mg_homeserver_public_uuid(), $ownerUserId, hash('sha256', $code), $expiresAt]);
    $pdo->commit();
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_fail_unexpected($error, 'homeserver.pairing_code_failed', 'Unable to create a HomeServer Sync Code.', 500, [], $ownerUserId);
}

mg_audit('homeserver.pairing_code_created', 'homeserver_device', [
    'expires_at' => $expiresAt,
    'device_limit' => $deviceLimit,
    'active_physical_device_count' => $activeDeviceCount,
    'new_physical_device_slot_available' => $newPhysicalDeviceSlotAvailable,
], $ownerUserId);
mg_ok([
    'pairing_code' => $code,
    'sync_code' => $code,
    'expires_at_utc' => gmdate(DATE_ATOM, strtotime($expiresAt . ' UTC')),
    'expires_in_seconds' => MG_HOMESERVER_PAIRING_TTL_SECONDS,
    'new_physical_device_slot_available' => $newPhysicalDeviceSlotAvailable,
    'entitlement' => mg_homeserver_entitlement_payload($pdo, $user, $entitlement),
], 'HomeServer Sync Code created.', 201);
