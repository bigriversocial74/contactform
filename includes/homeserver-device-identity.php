<?php
declare(strict_types=1);

/**
 * Physical HomeServer identity helpers.
 *
 * One installed HomeServer may carry multiple isolated provider/site connections.
 * Package limits therefore count distinct installation identities, not connection rows.
 */
if (!function_exists('mg_homeserver_active_device_count')) {
function mg_homeserver_active_device_count(PDO $pdo, int $ownerUserId): int
{
    if ($ownerUserId < 1) return 0;
    try {
        $stmt = $pdo->prepare("SELECT COUNT(DISTINCT installation_id) FROM homeserver_devices WHERE owner_user_id=? AND status='active'");
        $stmt->execute([$ownerUserId]);
        return max(0, (int)$stmt->fetchColumn());
    } catch (Throwable) {
        return 0;
    }
}
}

if (!function_exists('mg_homeserver_owner_has_installation')) {
function mg_homeserver_owner_has_installation(PDO $pdo, int $ownerUserId, string $installationId): bool
{
    if ($ownerUserId < 1 || !mg_homeserver_is_uuid($installationId)) return false;
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM homeserver_devices WHERE owner_user_id=? AND installation_id=? AND status='active'");
        $stmt->execute([$ownerUserId, strtolower($installationId)]);
        return (int)$stmt->fetchColumn() > 0;
    } catch (Throwable) {
        return false;
    }
}
}
