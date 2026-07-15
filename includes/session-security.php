<?php
/**
 * Hardened DB-backed session policy. Kept separate from the legacy security helper
 * so deployments can roll forward without redefining older public functions.
 */
declare(strict_types=1);

function mg_hardened_session_policy(array $roles = []): array
{
    $isAdmin = in_array('admin', $roles, true) || in_array('super_admin', $roles, true);
    $legacyDays = max(1, (int) mg_config_value('security', 'session_days', 30));
    if ($isAdmin) {
        $idleMinutes = max(5, (int) mg_config_value('security', 'session_admin_idle_minutes', 30));
        $absoluteMinutes = max($idleMinutes, (int) mg_config_value('security', 'session_admin_absolute_minutes', 480));
    } else {
        $idleMinutes = max(15, (int) mg_config_value('security', 'session_idle_minutes', 720));
        $absoluteMinutes = max($idleMinutes, (int) mg_config_value('security', 'session_absolute_minutes', $legacyDays * 1440));
    }
    return [
        'idle_minutes' => $idleMinutes,
        'absolute_minutes' => $absoluteMinutes,
        'rotation_minutes' => max(5, (int) mg_config_value('security', 'session_rotation_minutes', 15)),
    ];
}

function mg_hardened_session_schema_ready(): bool
{
    return function_exists('mg_identity_schema_has_column')
        && mg_identity_schema_has_column('user_sessions', 'auth_version')
        && mg_identity_schema_has_column('user_sessions', 'authentication_method')
        && mg_identity_schema_has_column('user_sessions', 'idle_expires_at')
        && mg_identity_schema_has_column('user_sessions', 'absolute_expires_at')
        && mg_identity_schema_has_column('user_sessions', 'last_rotated_at');
}

function mg_hardened_record_user_session(int $userId, int $authVersion = 1, array $roles = [], string $authenticationMethod = 'password'): void
{
    $policy = mg_hardened_session_policy($roles);
    $absoluteAt = gmdate('Y-m-d H:i:s', time() + $policy['absolute_minutes'] * 60);
    $idleAt = gmdate('Y-m-d H:i:s', time() + $policy['idle_minutes'] * 60);
    try {
        if (mg_hardened_session_schema_ready()) {
            $stmt = mg_db()->prepare(
                'INSERT INTO user_sessions
                 (user_id,session_hash,auth_version,authentication_method,ip_address,user_agent,last_seen_at,idle_expires_at,absolute_expires_at,last_rotated_at,expires_at,created_at)
                 VALUES (?,?,?,?,?,?,NOW(),?,?,NOW(),?,NOW())
                 ON DUPLICATE KEY UPDATE user_id=VALUES(user_id),auth_version=VALUES(auth_version),authentication_method=VALUES(authentication_method),ip_address=VALUES(ip_address),user_agent=VALUES(user_agent),last_seen_at=NOW(),idle_expires_at=VALUES(idle_expires_at),absolute_expires_at=VALUES(absolute_expires_at),last_rotated_at=NOW(),expires_at=VALUES(expires_at),revoked_at=NULL'
            );
            $stmt->execute([
                $userId,
                mg_current_session_hash(),
                max(1, $authVersion),
                in_array($authenticationMethod, ['password','mfa','recovery'], true) ? $authenticationMethod : 'password',
                mg_client_ip(),
                mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
                $idleAt,
                $absoluteAt,
                $absoluteAt,
            ]);
            return;
        }
        mg_record_user_session($userId);
    } catch (Throwable $e) {
        mg_security_log('critical', 'session.hardened_record_failed', 'Could not establish hardened authenticated session.', ['exception_class' => $e::class], $userId);
        throw new RuntimeException('Unable to establish a secure session.', 0, $e);
    }
}

function mg_hardened_session_validation_result(int $userId, int $authVersion = 1, array $roles = []): array
{
    try {
        if (!mg_hardened_session_schema_ready()) {
            return ['active' => mg_session_is_active($userId), 'reason' => 'legacy_schema'];
        }
        $stmt = mg_db()->prepare(
            'SELECT id,auth_version,last_seen_at,idle_expires_at,absolute_expires_at,expires_at,revoked_at
             FROM user_sessions
             WHERE user_id=? AND session_hash=? LIMIT 1'
        );
        $stmt->execute([$userId, mg_current_session_hash()]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return ['active' => false, 'reason' => 'missing'];
        if (!empty($row['revoked_at'])) return ['active' => false, 'reason' => 'revoked'];
        if ((int) ($row['auth_version'] ?? 1) !== max(1, $authVersion)) return ['active' => false, 'reason' => 'auth_version'];
        $now = time();
        foreach (['absolute_expires_at' => 'absolute_expired', 'idle_expires_at' => 'idle_expired', 'expires_at' => 'expired'] as $column => $reason) {
            if (!empty($row[$column])) {
                $expires = strtotime((string) $row[$column]);
                if ($expires !== false && $expires <= $now) return ['active' => false, 'reason' => $reason];
            }
        }
        $policy = mg_hardened_session_policy($roles);
        $idleAt = gmdate('Y-m-d H:i:s', $now + $policy['idle_minutes'] * 60);
        $touch = mg_db()->prepare('UPDATE user_sessions SET last_seen_at=NOW(),idle_expires_at=? WHERE id=? AND revoked_at IS NULL');
        $touch->execute([$idleAt, (int) $row['id']]);
        return ['active' => true, 'reason' => 'active'];
    } catch (Throwable $e) {
        mg_security_log('critical', 'session.hardened_validate_failed_closed', 'Hardened session validation failed closed.', ['exception_class' => $e::class], $userId);
        return ['active' => false, 'reason' => 'validator_error'];
    }
}

function mg_hardened_rotate_authenticated_session(int $userId, int $authVersion = 1, array $roles = []): bool
{
    if (headers_sent() || session_status() !== PHP_SESSION_ACTIVE) return false;
    $oldHash = mg_current_session_hash();
    if (!session_regenerate_id(true)) return false;
    $newHash = mg_current_session_hash();
    try {
        if (mg_hardened_session_schema_ready()) {
            $policy = mg_hardened_session_policy($roles);
            $idleAt = gmdate('Y-m-d H:i:s', time() + $policy['idle_minutes'] * 60);
            $stmt = mg_db()->prepare(
                'UPDATE user_sessions SET session_hash=?,auth_version=?,ip_address=?,user_agent=?,last_seen_at=NOW(),idle_expires_at=?,last_rotated_at=NOW()
                 WHERE user_id=? AND session_hash=? AND revoked_at IS NULL LIMIT 1'
            );
            $stmt->execute([
                $newHash,
                max(1, $authVersion),
                mg_client_ip(),
                mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
                $idleAt,
                $userId,
                $oldHash,
            ]);
            if ($stmt->rowCount() !== 1) return false;
        } else {
            $stmt = mg_db()->prepare('UPDATE user_sessions SET session_hash=?,last_seen_at=NOW() WHERE user_id=? AND session_hash=? AND revoked_at IS NULL LIMIT 1');
            $stmt->execute([$newHash, $userId, $oldHash]);
            if ($stmt->rowCount() !== 1) return false;
        }
        if (function_exists('mg_rotate_csrf_token')) mg_rotate_csrf_token();
        return true;
    } catch (Throwable $e) {
        mg_security_log('critical', 'session.rotation_failed', 'Authenticated session rotation failed.', ['exception_class' => $e::class], $userId);
        return false;
    }
}

function mg_hardened_revoke_user_sessions(PDO $pdo, int $userId, bool $strict = true): int
{
    try {
        $stmt = $pdo->prepare('UPDATE user_sessions SET revoked_at=NOW() WHERE user_id=? AND revoked_at IS NULL');
        $stmt->execute([$userId]);
        return $stmt->rowCount();
    } catch (Throwable $e) {
        mg_security_log($strict ? 'critical' : 'error', 'session.revoke_all_hardened_failed', 'Could not revoke all user sessions.', ['exception_class' => $e::class], $userId);
        if ($strict) throw $e;
        return 0;
    }
}

function mg_hardened_revoke_current_session(PDO $pdo, int $userId): int
{
    $stmt = $pdo->prepare('UPDATE user_sessions SET revoked_at=NOW() WHERE user_id=? AND session_hash=? AND revoked_at IS NULL');
    $stmt->execute([$userId, mg_current_session_hash()]);
    return $stmt->rowCount();
}

function mg_hardened_bump_auth_version(PDO $pdo, int $userId): int
{
    if (!function_exists('mg_identity_schema_has_column') || !mg_identity_schema_has_column('users', 'auth_version')) return 1;
    $stmt = $pdo->prepare('UPDATE users SET auth_version=auth_version+1,updated_at=NOW() WHERE id=?');
    $stmt->execute([$userId]);
    $read = $pdo->prepare('SELECT auth_version FROM users WHERE id=? LIMIT 1');
    $read->execute([$userId]);
    return max(1, (int) $read->fetchColumn());
}
