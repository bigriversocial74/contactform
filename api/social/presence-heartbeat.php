<?php
declare(strict_types=1);

require_once __DIR__ . '/_engagement.php';

mg_require_method('POST');
$user = mg_require_api_user();
$viewerId = (int)$user['id'];
$pdo = mg_db();

try {
    if (function_exists('mg_rate_limit')) {
        mg_rate_limit('social.presence_heartbeat', 'user:' . $viewerId, 120, 60);
    }
    if (function_exists('mg_session_is_active') && !mg_session_is_active($viewerId)) {
        mg_fail('Session expired.', 401);
    }
    $profile = null;
    try {
        $stmt = $pdo->prepare("SELECT pp.public_id,pp.display_name,pp.avatar_url,pp.profile_type,MAX(us.last_seen_at) last_seen_at,CASE WHEN MAX(us.last_seen_at)>=DATE_SUB(NOW(), INTERVAL 2 MINUTE) THEN 1 ELSE 0 END is_online FROM public_profiles pp LEFT JOIN user_sessions us ON us.user_id=pp.user_id AND us.revoked_at IS NULL AND us.expires_at>NOW() WHERE pp.user_id=? AND pp.status='active' AND pp.visibility IN ('public','unlisted') GROUP BY pp.id LIMIT 1");
        $stmt->execute([$viewerId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (is_array($row)) {
            $profile = [
                'id' => (string)$row['public_id'],
                'name' => (string)($row['display_name'] ?: 'Microgifter member'),
                'avatar_url' => isset($row['avatar_url']) ? (string)$row['avatar_url'] : null,
                'profile_type' => (string)($row['profile_type'] ?? 'profile'),
                'online' => (bool)($row['is_online'] ?? false),
                'last_seen_at' => (string)($row['last_seen_at'] ?? ''),
            ];
        }
    } catch (Throwable) {}
    mg_ok([
        'online' => true,
        'user_id' => $viewerId,
        'profile' => $profile,
        'checked_at' => gmdate('Y-m-d H:i:s'),
        'poll_after_ms' => 60000,
    ]);
} catch (Throwable $error) {
    mg_security_log('error', 'social.presence_heartbeat_failed', 'Social feed presence heartbeat failed.', ['exception_class' => $error::class, 'message' => $error->getMessage()], $viewerId);
    mg_fail('Unable to update presence.', 500);
}
