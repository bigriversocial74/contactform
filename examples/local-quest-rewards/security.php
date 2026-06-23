<?php
declare(strict_types=1);

function lqr_security_config(): array
{
    $path = __DIR__ . '/config.php';
    if (!is_file($path)) $path = __DIR__ . '/config.example.php';
    $config = require $path;
    $security = is_array($config['security'] ?? null) ? $config['security'] : [];
    return array_replace([
        'session_name' => 'LQRSESSID',
        'session_timeout_minutes' => 60,
        'csrf_field' => '_lqr_csrf',
        'csrf_ttl_minutes' => 120,
        'signed_code_ttl_minutes' => 15,
        'signed_code_secret' => '',
    ], $security);
}

function lqr_boot_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $security = lqr_security_config();
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_name((string)$security['session_name']);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
    if (empty($_SESSION['lqr_session_started_at'])) {
        $_SESSION['lqr_session_started_at'] = time();
        session_regenerate_id(true);
    }
    lqr_session_enforce_timeout();
}

function lqr_session_enforce_timeout(): void
{
    $security = lqr_security_config();
    $ttl = max(5, (int)$security['session_timeout_minutes']) * 60;
    $now = time();
    $last = (int)($_SESSION['lqr_last_seen_at'] ?? $now);
    if (($now - $last) > $ttl) {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool)$params['secure'], (bool)$params['httponly']);
        }
        session_destroy();
        session_start();
        session_regenerate_id(true);
        $_SESSION['lqr_session_started_at'] = $now;
    }
    $_SESSION['lqr_last_seen_at'] = $now;
}

function lqr_csrf_field(): string
{
    $security = lqr_security_config();
    return (string)$security['csrf_field'];
}

function lqr_csrf_token(): string
{
    $ttl = max(5, (int)lqr_security_config()['csrf_ttl_minutes']) * 60;
    $now = time();
    if (empty($_SESSION['lqr_csrf_token']) || empty($_SESSION['lqr_csrf_issued_at']) || ($now - (int)$_SESSION['lqr_csrf_issued_at']) > $ttl) {
        $_SESSION['lqr_csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['lqr_csrf_issued_at'] = $now;
    }
    return (string)$_SESSION['lqr_csrf_token'];
}

function lqr_csrf_input(): string
{
    return '<input type="hidden" name="' . htmlspecialchars(lqr_csrf_field(), ENT_QUOTES, 'UTF-8') . '" value="' . htmlspecialchars(lqr_csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function lqr_csrf_valid(?string $token): bool
{
    if ($token === null || $token === '') return false;
    $known = (string)($_SESSION['lqr_csrf_token'] ?? '');
    if ($known === '') return false;
    $ttl = max(5, (int)lqr_security_config()['csrf_ttl_minutes']) * 60;
    if ((time() - (int)($_SESSION['lqr_csrf_issued_at'] ?? 0)) > $ttl) return false;
    return hash_equals($known, $token);
}

function lqr_require_csrf(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') return;
    $field = lqr_csrf_field();
    $token = isset($_POST[$field]) ? (string)$_POST[$field] : '';
    if (!lqr_csrf_valid($token)) {
        http_response_code(419);
        throw new RuntimeException('Security token expired. Refresh the page and try again.');
    }
}

function lqr_auto_csrf_output(): void
{
    if (!empty($GLOBALS['lqr_auto_csrf_started'])) return;
    $GLOBALS['lqr_auto_csrf_started'] = true;
    ob_start(static function(string $html): string {
        if (stripos($html, '<form') === false) return $html;
        $input = lqr_csrf_input();
        return preg_replace_callback('/<form\b([^>]*)>/i', static function(array $m) use ($input): string {
            $tag = $m[0];
            $attrs = $m[1] ?? '';
            if (stripos($attrs, 'method="post"') === false && stripos($attrs, "method='post'") === false && stripos($attrs, 'method=post') === false) return $tag;
            if (stripos($tag, 'data-lqr-no-csrf') !== false) return $tag;
            return $tag . $input;
        }, $html) ?? $html;
    });
}

function lqr_security_secret(array $config): string
{
    $security = is_array($config['security'] ?? null) ? $config['security'] : [];
    $secret = (string)($security['signed_code_secret'] ?? '');
    if ($secret !== '' && !str_contains($secret, 'replace_with')) return $secret;
    return hash('sha256', (string)($config['api_key'] ?? '') . '|' . (string)($config['webhook_secret'] ?? '') . '|local-quest');
}

function lqr_signed_payload(array $config, array $payload): string
{
    $payload['iat'] = $payload['iat'] ?? time();
    $payload['nonce'] = $payload['nonce'] ?? bin2hex(random_bytes(8));
    ksort($payload);
    $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($json)) throw new RuntimeException('Unable to encode signed payload.');
    $body = rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    $sig = hash_hmac('sha256', $body, lqr_security_secret($config));
    return 'lqr1.' . $body . '.' . $sig;
}

function lqr_verify_signed_payload(array $config, string $code, string $expectedType = ''): array
{
    $parts = explode('.', trim($code));
    if (count($parts) !== 3 || $parts[0] !== 'lqr1') throw new RuntimeException('Invalid signed code format.');
    [$version, $body, $sig] = $parts;
    $expected = hash_hmac('sha256', $body, lqr_security_secret($config));
    if (!hash_equals($expected, $sig)) throw new RuntimeException('Signed code verification failed.');
    $json = base64_decode(strtr($body, '-_', '+/'), true);
    $payload = json_decode((string)$json, true);
    if (!is_array($payload)) throw new RuntimeException('Signed code payload is invalid.');
    if ($expectedType !== '' && (string)($payload['type'] ?? '') !== $expectedType) throw new RuntimeException('Signed code type mismatch.');
    $ttl = max(1, (int)lqr_security_config()['signed_code_ttl_minutes']) * 60;
    if (time() - (int)($payload['iat'] ?? 0) > $ttl) throw new RuntimeException('Signed code has expired.');
    return $payload;
}

function lqr_replay_key(array $payload): string
{
    return hash('sha256', (string)($payload['type'] ?? '') . '|' . (string)($payload['quest_id'] ?? '') . '|' . (string)($payload['nonce'] ?? '') . '|' . (string)($payload['iat'] ?? ''));
}

function lqr_replay_seen(array $state, array $payload): bool
{
    $key = lqr_replay_key($payload);
    return !empty($state['security_replay'][$key]);
}

function lqr_mark_replay(array &$state, array $payload): void
{
    if (!isset($state['security_replay']) || !is_array($state['security_replay'])) $state['security_replay'] = [];
    $state['security_replay'][lqr_replay_key($payload)] = gmdate('c');
    if (count($state['security_replay']) > 500) $state['security_replay'] = array_slice($state['security_replay'], -250, null, true);
}
