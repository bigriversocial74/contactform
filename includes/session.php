<?php
declare(strict_types=1);

/**
 * Central session bootstrap for every first-party page and API endpoint.
 *
 * Session policy must be applied before session_start() so the browser never
 * receives a cookie created with hosting-provider defaults.
 */

if (!function_exists('mg_session_config')) {
    function mg_session_config(?array $config = null): array
    {
        if (is_array($config)) {
            return $config;
        }

        static $loaded = null;
        if (is_array($loaded)) {
            return $loaded;
        }

        $path = dirname(__DIR__) . '/api/config.php';
        $value = is_file($path) ? require $path : [];
        $loaded = is_array($value) ? $value : [];
        return $loaded;
    }
}

if (!function_exists('mg_request_is_https')) {
    function mg_request_is_https(?array $server = null, ?array $config = null): bool
    {
        $server = $server ?? $_SERVER;
        $config = mg_session_config($config);

        $https = strtolower((string) ($server['HTTPS'] ?? ''));
        if ($https !== '' && $https !== 'off' && $https !== '0') {
            return true;
        }
        if (strtolower((string) ($server['REQUEST_SCHEME'] ?? '')) === 'https') {
            return true;
        }
        if ((string) ($server['SERVER_PORT'] ?? '') === '443') {
            return true;
        }

        if ((bool) ($config['app']['trust_proxy'] ?? false)) {
            $forwardedProto = strtolower(trim(explode(',', (string) ($server['HTTP_X_FORWARDED_PROTO'] ?? ''))[0] ?? ''));
            if ($forwardedProto === 'https') {
                return true;
            }

            $forwarded = (string) ($server['HTTP_FORWARDED'] ?? '');
            if ($forwarded !== '' && preg_match('/(?:^|[;,]\s*)proto=https(?:[;,]|$)/i', $forwarded) === 1) {
                return true;
            }
        }

        $baseUrl = (string) ($config['app']['base_url'] ?? '');
        return $baseUrl !== '' && strtolower((string) parse_url($baseUrl, PHP_URL_SCHEME)) === 'https';
    }
}

if (!function_exists('mg_session_cookie_options')) {
    function mg_session_cookie_options(?array $config = null, ?array $server = null): array
    {
        $config = mg_session_config($config);
        $security = is_array($config['security'] ?? null) ? $config['security'] : [];
        $days = max(1, (int) ($security['session_days'] ?? 30));

        $secureSetting = strtolower(trim((string) ($security['session_cookie_secure'] ?? 'auto')));
        $secure = match ($secureSetting) {
            '1', 'true', 'yes', 'on' => true,
            '0', 'false', 'no', 'off' => false,
            default => mg_request_is_https($server, $config),
        };

        $sameSite = ucfirst(strtolower(trim((string) ($security['session_cookie_samesite'] ?? 'Lax'))));
        if (!in_array($sameSite, ['Lax', 'Strict', 'None'], true)) {
            throw new RuntimeException('MG_SESSION_COOKIE_SAMESITE must be Lax, Strict, or None.');
        }
        if ($sameSite === 'None' && !$secure) {
            throw new RuntimeException('SameSite=None requires a secure session cookie.');
        }

        $path = trim((string) ($security['session_cookie_path'] ?? '/'));
        if ($path === '' || $path[0] !== '/') {
            throw new RuntimeException('MG_SESSION_COOKIE_PATH must begin with /.');
        }

        return [
            'lifetime' => $days * 86400,
            'path' => $path,
            'domain' => trim((string) ($security['session_cookie_domain'] ?? '')),
            'secure' => $secure,
            'httponly' => true,
            'samesite' => $sameSite,
        ];
    }
}

if (!function_exists('mg_session_name')) {
    function mg_session_name(?array $config = null): string
    {
        $config = mg_session_config($config);
        $name = trim((string) ($config['security']['session_name'] ?? 'mg_session'));
        if (preg_match('/^[A-Za-z][A-Za-z0-9_-]{0,127}$/', $name) !== 1) {
            throw new RuntimeException('MG_SESSION_NAME contains unsupported characters.');
        }
        return $name;
    }
}

if (!function_exists('mg_start_session')) {
    function mg_start_session(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        if (headers_sent($file, $line)) {
            throw new RuntimeException("Session cannot start after output at {$file}:{$line}.");
        }

        $options = mg_session_cookie_options();
        session_name(mg_session_name());

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_secure', $options['secure'] ? '1' : '0');
        ini_set('session.cookie_samesite', $options['samesite']);
        ini_set('session.cookie_lifetime', (string) $options['lifetime']);
        ini_set('session.gc_maxlifetime', (string) $options['lifetime']);

        session_set_cookie_params($options);
        if (!session_start()) {
            throw new RuntimeException('Unable to start a secure session.');
        }
    }
}

if (!function_exists('mg_regenerate_session_id')) {
    function mg_regenerate_session_id(): void
    {
        mg_start_session();
        if (!session_regenerate_id(true)) {
            throw new RuntimeException('Unable to rotate the session identifier.');
        }
    }
}

if (!function_exists('mg_destroy_session')) {
    function mg_destroy_session(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $_SESSION = [];
        $usesCookies = filter_var((string) ini_get('session.use_cookies'), FILTER_VALIDATE_BOOLEAN);
        if ($usesCookies && !headers_sent()) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => (string) ($params['path'] ?? '/'),
                'domain' => (string) ($params['domain'] ?? ''),
                'secure' => (bool) ($params['secure'] ?? false),
                'httponly' => (bool) ($params['httponly'] ?? true),
                'samesite' => (string) ($params['samesite'] ?? 'Lax'),
            ]);
        }

        if (!session_destroy()) {
            throw new RuntimeException('Unable to destroy the session.');
        }
        session_id('');
    }
}
