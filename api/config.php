<?php
/**
 * Microgifter API configuration.
 *
 * Production rule: secrets must come from environment variables or a server-local
 * ignored config layer. Do not commit real credentials to GitHub.
 */
declare(strict_types=1);

if (!function_exists('mg_env')) {
    function mg_env(string $key, mixed $default = null): mixed
    {
        $value = getenv($key);
        if ($value === false || $value === '') {
            return $default;
        }
        return $value;
    }
}

if (!function_exists('mg_env_bool')) {
    function mg_env_bool(string $key, bool $default = false): bool
    {
        $value = mg_env($key, null);
        if ($value === null) {
            return $default;
        }
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}

if (!function_exists('mg_env_int')) {
    function mg_env_int(string $key, int $default): int
    {
        $value = mg_env($key, null);
        if ($value === null || !is_numeric($value)) {
            return $default;
        }
        return max(0, (int) $value);
    }
}

if (!function_exists('mg_array_deep_merge')) {
    function mg_array_deep_merge(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
                $base[$key] = mg_array_deep_merge($base[$key], $value);
                continue;
            }
            $base[$key] = $value;
        }
        return $base;
    }
}

$baseUrl = rtrim((string) mg_env('MG_BASE_URL', ''), '/');
$appEnv = strtolower((string) mg_env('MG_APP_ENV', 'production'));
$runtimeProfile = strtolower((string) mg_env('MG_RUNTIME_PROFILE', 'hostgator'));
$applicationRoot = dirname(__DIR__);
$normalizedApplicationRoot = str_replace('\\', '/', $applicationRoot);
$defaultPersistentMediaRoot = dirname($applicationRoot) . '/microgifter-storage';
if (preg_match('#^(.*)/(public_html|www|htdocs)(?:/|$)#', $normalizedApplicationRoot, $webRootMatch) === 1) {
    $hostingHome = rtrim((string)$webRootMatch[1], '/');
    if ($hostingHome !== '') {
        $defaultPersistentMediaRoot = $hostingHome . '/microgifter-storage';
    }
}

$config = [
    'db' => [
        'host' => (string) mg_env('MG_DB_HOST', 'localhost'),
        'name' => (string) mg_env('MG_DB_NAME', 'microgifter'),
        'user' => (string) mg_env('MG_DB_USER', 'microgifter_user'),
        'pass' => (string) mg_env('MG_DB_PASS', ''),
        'charset' => (string) mg_env('MG_DB_CHARSET', 'utf8mb4'),
    ],
    'app' => [
        'env' => $appEnv,
        'debug' => mg_env_bool('MG_DEBUG', false),
        'base_url' => $baseUrl,
        'trust_proxy' => mg_env_bool('MG_TRUST_PROXY', false),
    ],
    'runtime' => [
        'profile' => $runtimeProfile,
    ],
    'storage' => [
        'driver' => (string) mg_env('MG_MEDIA_STORAGE_DRIVER', 'persistent_local'),
        'root' => (string) mg_env('MG_MEDIA_STORAGE_ROOT', $defaultPersistentMediaRoot),
        'public_endpoint' => (string) mg_env('MG_MEDIA_PUBLIC_ENDPOINT', '/api/public/media.php'),
        'require_persistent' => mg_env_bool('MG_REQUIRE_PERSISTENT_MEDIA_STORAGE', $appEnv === 'production'),
        'legacy_root' => $applicationRoot,
    ],
    'features' => [
        'polling_notifications' => mg_env_bool('MG_ENABLE_POLLING_NOTIFICATIONS', true),
        'db_outbox' => mg_env_bool('MG_ENABLE_DB_OUTBOX', true),
        'queue_worker' => mg_env_bool('MG_ENABLE_QUEUE_WORKER', false),
        'redis' => mg_env_bool('MG_ENABLE_REDIS', false),
        'websockets' => mg_env_bool('MG_ENABLE_WEBSOCKETS', false),
        'sse' => mg_env_bool('MG_ENABLE_SSE', false),
        'pwa_push' => mg_env_bool('MG_ENABLE_PWA_PUSH', true),
    ],
    'delivery' => [
        'poll_interval_seconds' => mg_env_int('MG_POLL_INTERVAL_SECONDS', 15),
        'poll_fast_interval_seconds' => mg_env_int('MG_POLL_FAST_INTERVAL_SECONDS', 5),
        'tracking_event_retention_days' => mg_env_int('MG_TRACKING_EVENT_RETENTION_DAYS', 365),
        'pwa_push_batch_size' => mg_env_int('MG_PWA_PUSH_BATCH_SIZE', 25),
    ],
    'pwa_push' => [
        'vapid_public_key' => (string) mg_env('MG_PWA_VAPID_PUBLIC_KEY', ''),
        'vapid_private_key' => (string) mg_env('MG_PWA_VAPID_PRIVATE_KEY', ''),
        'vapid_subject' => (string) mg_env('MG_PWA_VAPID_SUBJECT', 'mailto:admin@microgifter.com'),
    ],
    'security' => [
        'session_name' => (string) mg_env('MG_SESSION_NAME', 'mg_session'),
        'session_days' => mg_env_int('MG_SESSION_DAYS', 30),
        'reset_token_minutes' => mg_env_int('MG_RESET_TOKEN_MINUTES', 60),
        'verify_token_minutes' => mg_env_int('MG_VERIFY_TOKEN_MINUTES', 1440),
        'claim_code_pepper' => (string) mg_env('MG_CLAIM_CODE_PEPPER', ''),
        'rate_limit_login_max' => mg_env_int('MG_RATE_LIMIT_LOGIN_MAX', 8),
        'rate_limit_login_window' => mg_env_int('MG_RATE_LIMIT_LOGIN_WINDOW', 900),
        'rate_limit_register_max' => mg_env_int('MG_RATE_LIMIT_REGISTER_MAX', 10),
        'rate_limit_register_window' => mg_env_int('MG_RATE_LIMIT_REGISTER_WINDOW', 3600),
        'rate_limit_recovery_max' => mg_env_int('MG_RATE_LIMIT_RECOVERY_MAX', 5),
        'rate_limit_recovery_window' => mg_env_int('MG_RATE_LIMIT_RECOVERY_WINDOW', 3600),
        'rate_limit_profile_max' => mg_env_int('MG_RATE_LIMIT_PROFILE_MAX', 20),
        'rate_limit_profile_window' => mg_env_int('MG_RATE_LIMIT_PROFILE_WINDOW', 3600),
    ],
];

$localConfigFile = __DIR__ . '/config.local.php';
if (is_file($localConfigFile)) {
    $localConfig = require $localConfigFile;
    if (is_array($localConfig)) {
        $config = mg_array_deep_merge($config, $localConfig);
    }
}

return $config;
