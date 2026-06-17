<?php
/**
 * Microgifter security helper layer.
 */
declare(strict_types=1);

if (!function_exists('mg_api_config')) {
    function mg_api_config(): array
    {
        static $config = null;
        if (is_array($config)) {
            return $config;
        }
        $config = require __DIR__ . '/config.php';
        return is_array($config) ? $config : [];
    }
}

if (!function_exists('mg_config_value')) {
    function mg_config_value(string $section, string $key, mixed $default = null): mixed
    {
        $config = mg_api_config();
        return $config[$section][$key] ?? $default;
    }
}

function mg_request_id(): string
{
    static $requestId = null;
    if (is_string($requestId)) {
        return $requestId;
    }
    $incoming = $_SERVER['HTTP_X_REQUEST_ID'] ?? '';
    $incoming = is_string($incoming) ? preg_replace('/[^a-zA-Z0-9_\-.]/', '', $incoming) : '';
    $requestId = $incoming !== '' ? substr($incoming, 0, 80) : bin2hex(random_bytes(16));
    return $requestId;
}

function mg_client_ip(): ?string
{
    $trustProxy = (bool) mg_config_value('app', 'trust_proxy', false);
    if ($trustProxy && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $parts = explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR']);
        $candidate = trim($parts[0] ?? '');
        if (filter_var($candidate, FILTER_VALIDATE_IP)) {
            return $candidate;
        }
    }
    $remote = $_SERVER['REMOTE_ADDR'] ?? null;
    return is_string($remote) && filter_var($remote, FILTER_VALIDATE_IP) ? $remote : null;
}

function mg_apply_api_security_headers(): void
{
    header('X-Request-ID: ' . mg_request_id());
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
    header('Cross-Origin-Opener-Policy: same-origin');
    header('Cross-Origin-Resource-Policy: same-origin');
    header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'; base-uri 'none'; form-action 'self'");
    header('Cache-Control: no-store, private');

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((bool) mg_config_value('app', 'trust_proxy', false) && (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'));
    if ($isHttps) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
    }
}

/**
 * Remove credentials and high-risk values before writing structured security logs.
 */
function mg_redact_security_context(mixed $value, ?string $key = null): mixed
{
    $normalizedKey = strtolower((string) $key);
    $sensitiveFragments = [
        'password', 'passwd', 'secret', 'token', 'authorization', 'cookie', 'session',
        'csrf', 'claim_code', 'claimcode', 'api_key', 'apikey', 'private_key', 'access_key',
        'refresh_token', 'reset_code', 'verification_code', 'payment_method', 'card_number', 'cvv',
    ];

    foreach ($sensitiveFragments as $fragment) {
        if ($normalizedKey !== '' && str_contains($normalizedKey, $fragment)) {
            return '[REDACTED]';
        }
    }

    if (is_array($value)) {
        $redacted = [];
        foreach ($value as $childKey => $childValue) {
            $redacted[$childKey] = mg_redact_security_context($childValue, (string) $childKey);
        }
        return $redacted;
    }

    if (is_object($value)) {
        return '[OBJECT]';
    }

    if (is_string($value)) {
        $value = preg_replace('/Bearer\s+[A-Za-z0-9._~+\/-]+=*/i', 'Bearer [REDACTED]', $value) ?? $value;
        $value = preg_replace('/([?&](?:token|key|secret|code)=)[^&\s]+/i', '$1[REDACTED]', $value) ?? $value;
        $value = preg_replace('/:\/\/([^:\/@\s]+):([^@\/\s]+)@/', '://[REDACTED]:[REDACTED]@', $value) ?? $value;
        return mb_substr($value, 0, 2000);
    }

    return $value;
}

function mg_security_log(string $severity, string $eventType, string $message, array $context = [], ?int $userId = null): void
{
    $safeContext = mg_redact_security_context($context);
    $payload = [
        'time' => gmdate('c'),
        'severity' => $severity,
        'event_type' => $eventType,
        'request_id' => mg_request_id(),
        'user_id' => $userId ?? (function_exists('mg_current_user') ? (mg_current_user()['id'] ?? null) : null),
        'message' => mb_substr((string) mg_redact_security_context($message), 0, 255),
        'context' => $safeContext,
        'ip_address' => mg_client_ip(),
    ];
    $line = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (is_string($line)) {
        error_log($line);
    }

    try {
        $stmt = mg_db()->prepare(
            'INSERT INTO security_logs (severity, event_type, user_id, request_id, message, context_json, ip_address, user_agent, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            in_array($severity, ['debug','info','warning','error','critical'], true) ? $severity : 'info',
            $eventType,
            $payload['user_id'],
            mg_request_id(),
            $payload['message'],
            json_encode($safeContext, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            mg_client_ip(),
            mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
        ]);
    } catch (Throwable $e) {
        // The server error log above is the fallback. Do not recurse.
    }
}

/**
 * Apply a database-backed rate limit.
 *
 * Sensitive operations fail closed when the limiter cannot be evaluated. This prevents
 * authentication, recovery, claim, or provider-cost controls from silently disappearing.
 */
function mg_rate_limit(string $action, string $identifier, int $maxAttempts, int $windowSeconds): void
{
    $identifierHash = hash('sha256', strtolower(trim($identifier)));
    $now = time();
    $windowSeconds = max(60, $windowSeconds);
    $maxAttempts = max(1, $maxAttempts);

    try {
        $pdo = mg_db();
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('SELECT id, attempts, first_seen_at, locked_until FROM rate_limits WHERE action = ? AND identifier_hash = ? FOR UPDATE');
        $stmt->execute([$action, $identifierHash]);
        $row = $stmt->fetch();

        if (!$row) {
            $insert = $pdo->prepare('INSERT INTO rate_limits (action, identifier_hash, attempts, first_seen_at, last_seen_at, created_at, updated_at) VALUES (?, ?, 1, NOW(), NOW(), NOW(), NOW())');
            $insert->execute([$action, $identifierHash]);
            $pdo->commit();
            return;
        }

        $lockedUntil = !empty($row['locked_until']) ? strtotime((string) $row['locked_until']) : false;
        if ($lockedUntil && $lockedUntil > $now) {
            $pdo->commit();
            mg_security_log('warning', 'rate_limit.blocked', 'Rate limit blocked request.', ['action' => $action]);
            mg_fail('Too many attempts. Please try again later.', 429);
        }

        $firstSeen = strtotime((string) $row['first_seen_at']) ?: $now;
        if (($now - $firstSeen) > $windowSeconds) {
            $reset = $pdo->prepare('UPDATE rate_limits SET attempts = 1, first_seen_at = NOW(), last_seen_at = NOW(), locked_until = NULL WHERE id = ?');
            $reset->execute([(int) $row['id']]);
            $pdo->commit();
            return;
        }

        $attempts = (int) $row['attempts'] + 1;
        $lockUntil = $attempts > $maxAttempts ? gmdate('Y-m-d H:i:s', $now + $windowSeconds) : null;
        $update = $pdo->prepare('UPDATE rate_limits SET attempts = ?, last_seen_at = NOW(), locked_until = ? WHERE id = ?');
        $update->execute([$attempts, $lockUntil, (int) $row['id']]);
        $pdo->commit();

        if ($attempts > $maxAttempts) {
            mg_security_log('warning', 'rate_limit.locked', 'Rate limit locked identifier.', ['action' => $action]);
            mg_fail('Too many attempts. Please try again later.', 429);
        }
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        mg_security_log('critical', 'rate_limit.failed_closed', 'Rate limit check unavailable; request denied.', [
            'action' => $action,
            'exception_class' => $e::class,
        ]);
        mg_fail('Security service temporarily unavailable. Please try again shortly.', 503);
    }
}

function mg_rate_limit_clear(string $action, string $identifier): void
{
    try {
        $stmt = mg_db()->prepare('DELETE FROM rate_limits WHERE action = ? AND identifier_hash = ?');
        $stmt->execute([$action, hash('sha256', strtolower(trim($identifier)))]);
    } catch (Throwable $e) {
        mg_security_log('error', 'rate_limit.clear_failed', 'Could not clear rate limit.', [
            'action' => $action,
            'exception_class' => $e::class,
        ]);
    }
}

function mg_current_session_hash(): string
{
    return hash('sha256', session_id());
}

function mg_record_user_session(int $userId): void
{
    try {
        $sessionDays = (int) mg_config_value('security', 'session_days', 30);
        $expiresAt = gmdate('Y-m-d H:i:s', time() + max(1, $sessionDays) * 86400);
        $stmt = mg_db()->prepare(
            'INSERT INTO user_sessions (user_id, session_hash, ip_address, user_agent, last_seen_at, expires_at, created_at)
             VALUES (?, ?, ?, ?, NOW(), ?, NOW())
             ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), ip_address = VALUES(ip_address), user_agent = VALUES(user_agent), last_seen_at = NOW(), expires_at = VALUES(expires_at), revoked_at = NULL'
        );
        $stmt->execute([
            $userId,
            mg_current_session_hash(),
            mg_client_ip(),
            mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            $expiresAt,
        ]);
    } catch (Throwable $e) {
        mg_security_log('critical', 'session.record_failed', 'Could not record authenticated session.', [
            'exception_class' => $e::class,
        ], $userId);
        throw new RuntimeException('Unable to establish a secure session.', 0, $e);
    }
}

function mg_session_is_active(int $userId): bool
{
    try {
        $stmt = mg_db()->prepare('SELECT id FROM user_sessions WHERE user_id = ? AND session_hash = ? AND revoked_at IS NULL AND (expires_at IS NULL OR expires_at > NOW()) LIMIT 1');
        $stmt->execute([$userId, mg_current_session_hash()]);
        if (!$stmt->fetch()) {
            return false;
        }
        $touch = mg_db()->prepare('UPDATE user_sessions SET last_seen_at = NOW() WHERE user_id = ? AND session_hash = ? LIMIT 1');
        $touch->execute([$userId, mg_current_session_hash()]);
        return true;
    } catch (Throwable $e) {
        mg_security_log('critical', 'session.validate_failed_closed', 'Could not validate user session; access denied.', [
            'exception_class' => $e::class,
        ], $userId);
        return false;
    }
}

function mg_revoke_current_session(?int $userId = null): void
{
    try {
        if ($userId) {
            $stmt = mg_db()->prepare('UPDATE user_sessions SET revoked_at = NOW() WHERE session_hash = ? AND user_id = ?');
            $stmt->execute([mg_current_session_hash(), $userId]);
            return;
        }
        $stmt = mg_db()->prepare('UPDATE user_sessions SET revoked_at = NOW() WHERE session_hash = ?');
        $stmt->execute([mg_current_session_hash()]);
    } catch (Throwable $e) {
        mg_security_log('error', 'session.revoke_failed', 'Could not revoke current session.', [
            'exception_class' => $e::class,
        ], $userId);
    }
}

function mg_revoke_user_sessions(int $userId): void
{
    try {
        $stmt = mg_db()->prepare('UPDATE user_sessions SET revoked_at = NOW() WHERE user_id = ? AND revoked_at IS NULL');
        $stmt->execute([$userId]);
    } catch (Throwable $e) {
        mg_security_log('error', 'session.revoke_all_failed', 'Could not revoke user sessions.', [
            'exception_class' => $e::class,
        ], $userId);
    }
}
