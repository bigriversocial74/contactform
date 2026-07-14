<?php
declare(strict_types=1);

$rdRoot = dirname(__DIR__, 3);
require_once $rdRoot . '/includes/app.php';
require_once $rdRoot . '/api/db.php';

function rd_env(string $key, string $default = ''): string
{
    $value = getenv($key);
    return is_string($value) && trim($value) !== '' ? trim($value) : $default;
}

function rd_config(): array
{
    static $config = null;
    if (is_array($config)) return $config;

    $target = max(5, min(50, (int)rd_env('MG_REWARD_DROP_TARGET_SCORE', '12')));
    $duration = max(12, min(60, (int)rd_env('MG_REWARD_DROP_DURATION_SECONDS', '20')));
    $minimumPlay = max(5, min($duration, (int)rd_env('MG_REWARD_DROP_MIN_PLAY_SECONDS', '8')));
    $cooldown = max(1, min(720, (int)rd_env('MG_REWARD_DROP_REWARD_COOLDOWN_HOURS', '24')));
    $apiKey = rd_env('MG_REWARD_DROP_API_KEY');

    $config = [
        'base_url' => rtrim(rd_env('MG_REWARD_DROP_API_BASE_URL', 'https://microgifter.com'), '/'),
        'public_url' => rtrim(rd_env('MG_REWARD_DROP_PUBLIC_URL', 'https://microgifter.com/games/reward-drop'), '/'),
        'api_key' => $apiKey,
        'program_id' => rd_env('MG_REWARD_DROP_PROGRAM_ID'),
        'template_id' => rd_env('MG_REWARD_DROP_TEMPLATE_ID'),
        'webhook_secret' => rd_env('MG_REWARD_DROP_WEBHOOK_SECRET'),
        'state_key' => rd_env('MG_REWARD_DROP_STATE_KEY', $apiKey !== '' ? hash('sha256', 'reward-drop-state|' . $apiKey) : ''),
        'target_score' => $target,
        'duration_seconds' => $duration,
        'minimum_play_seconds' => $minimumPlay,
        'cooldown_hours' => $cooldown,
        'run_ttl_seconds' => max($duration + 60, 180),
    ];
    return $config;
}

function rd_configured(array $config): bool
{
    foreach (['api_key','program_id','template_id','webhook_secret','state_key'] as $key) {
        $value = trim((string)($config[$key] ?? ''));
        if ($value === '' || str_contains($value, 'replace_')) return false;
    }
    return true;
}

function rd_json(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, private');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function rd_input(): array
{
    $contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
    if (str_contains($contentType, 'application/json')) {
        $decoded = json_decode(file_get_contents('php://input') ?: '{}', true);
        return is_array($decoded) ? $decoded : [];
    }
    return $_POST;
}

function rd_require_post(): void
{
    if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
        rd_json(['ok' => false, 'message' => 'POST is required.'], 405);
    }
}

function rd_session_user(): ?array
{
    $user = mg_current_user();
    if (!is_array($user) || (int)($user['id'] ?? 0) < 1) return null;
    return $user;
}

function rd_require_user_json(): array
{
    $user = rd_session_user();
    if ($user === null) rd_json(['ok' => false, 'message' => 'Sign in to Microgifter to play.', 'signin_url' => '/signin.php?return=' . rawurlencode('/games/reward-drop/')], 401);
    return $user;
}

function rd_require_csrf(array $input): void
{
    $token = (string)($input['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
    if (!mg_verify_csrf($token)) rd_json(['ok' => false, 'message' => 'Your session token expired. Refresh and try again.'], 419);
}

function rd_b64url_encode(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function rd_b64url_decode(string $value): string|false
{
    $padding = strlen($value) % 4;
    if ($padding > 0) $value .= str_repeat('=', 4 - $padding);
    return base64_decode(strtr($value, '-_', '+/'), true);
}

function rd_state_create(int $userId, string $externalUserId, array $config): string
{
    $payload = json_encode([
        'uid' => $userId,
        'external' => $externalUserId,
        'exp' => time() + 1800,
        'nonce' => bin2hex(random_bytes(16)),
    ], JSON_UNESCAPED_SLASHES);
    if (!is_string($payload)) throw new RuntimeException('Unable to create account-link state.');
    $encoded = rd_b64url_encode($payload);
    $signature = hash_hmac('sha256', $encoded, (string)$config['state_key']);
    return $encoded . '.' . $signature;
}

function rd_state_verify(string $state, int $userId, array $config): ?array
{
    $parts = explode('.', $state, 2);
    if (count($parts) !== 2 || (string)$config['state_key'] === '') return null;
    [$encoded, $signature] = $parts;
    $expected = hash_hmac('sha256', $encoded, (string)$config['state_key']);
    if (!hash_equals($expected, $signature)) return null;
    $raw = rd_b64url_decode($encoded);
    if (!is_string($raw)) return null;
    $payload = json_decode($raw, true);
    if (!is_array($payload) || (int)($payload['uid'] ?? 0) !== $userId || (int)($payload['exp'] ?? 0) < time()) return null;
    return $payload;
}

function rd_external_user_id(int $userId): string
{
    return 'microgifter-user-' . $userId;
}

function rd_uuid(): string
{
    return function_exists('mg_public_uuid') ? mg_public_uuid() : sprintf(
        '%s-%s-4%s-%s%s-%s',
        bin2hex(random_bytes(4)),
        bin2hex(random_bytes(2)),
        bin2hex(random_bytes(2)),
        dechex((random_int(0, 3) | 8)),
        bin2hex(random_bytes(1)),
        bin2hex(random_bytes(6))
    );
}

function rd_app_context(PDO $pdo, array $config): ?array
{
    $apiKey = (string)($config['api_key'] ?? '');
    if ($apiKey === '') return null;
    $stmt = $pdo->prepare("SELECT mda.id,mda.public_id,mda.name,mda.environment AS app_environment,mda.status AS app_status,mda.webhook_url,mda.allowed_origins_json,mak.environment AS key_environment,mak.status AS key_status,mak.scopes_json FROM merchant_api_keys mak INNER JOIN merchant_developer_apps mda ON mda.id=mak.app_id WHERE mak.key_hash=? LIMIT 1");
    $stmt->execute([hash('sha256', $apiKey)]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return null;
    $scopes = json_decode((string)($row['scopes_json'] ?? '[]'), true);
    $row['scopes'] = is_array($scopes) ? $scopes : [];
    return $row;
}

function rd_readiness(PDO $pdo, array $config): array
{
    $app = rd_app_context($pdo, $config);
    $requiredScopes = ['distribution:rewards.issue','distribution:rewards.status'];
    $scopes = is_array($app['scopes'] ?? null) ? $app['scopes'] : [];
    return [
        'configured' => rd_configured($config),
        'credential_found' => is_array($app),
        'app_live' => is_array($app) && ($app['app_status'] ?? '') === 'active' && ($app['app_environment'] ?? '') === 'live',
        'key_live' => is_array($app) && ($app['key_status'] ?? '') === 'active' && ($app['key_environment'] ?? '') === 'live',
        'scopes_ready' => count(array_diff($requiredScopes, $scopes)) === 0,
        'webhook_url_ready' => is_array($app) && trim((string)($app['webhook_url'] ?? '')) !== '',
        'webhook_secret_ready' => trim((string)($config['webhook_secret'] ?? '')) !== '',
        'program_id_ready' => trim((string)($config['program_id'] ?? '')) !== '',
        'template_id_ready' => trim((string)($config['template_id'] ?? '')) !== '',
        'app_name' => is_array($app) ? (string)$app['name'] : null,
        'app_id' => is_array($app) ? (int)$app['id'] : null,
    ];
}

function rd_active_link(PDO $pdo, int $appId, int $userId): ?array
{
    $stmt = $pdo->prepare("SELECT public_id,external_user_id,linked_at FROM developer_app_user_links WHERE app_id=? AND microgifter_user_id=? AND status='active' LIMIT 1");
    $stmt->execute([$appId, $userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function rd_api_request(array $config, string $method, string $path, ?array $body = null, array $headers = []): array
{
    $url = rtrim((string)$config['base_url'], '/') . $path;
    $requestHeaders = array_merge([
        'Authorization: Bearer ' . (string)$config['api_key'],
        'Accept: application/json',
        'User-Agent: Microgifter-Reward-Drop/1.0',
    ], $headers);
    $payload = null;
    if ($body !== null) {
        $payload = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($payload)) throw new RuntimeException('Unable to encode the API request.');
        $requestHeaders[] = 'Content-Type: application/json';
    }

    if (!function_exists('curl_init')) throw new RuntimeException('The server-side cURL extension is required.');
    $ch = curl_init($url);
    if ($ch === false) throw new RuntimeException('Unable to initialize the API request.');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_HTTPHEADER => $requestHeaders,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 18,
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    if ($payload !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    $raw = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    if ($raw === false) throw new RuntimeException($error !== '' ? $error : 'Microgifter API request failed.');
    $decoded = json_decode((string)$raw, true);
    $response = is_array($decoded) ? $decoded : ['message' => (string)$raw];
    $data = is_array($response['data'] ?? null) ? $response['data'] : $response;
    if ($status < 200 || $status >= 300) {
        $message = (string)($response['message'] ?? $data['message'] ?? 'Microgifter API request failed.');
        throw new RuntimeException($message !== '' ? $message : 'Microgifter API request failed.');
    }
    return ['status' => $status, 'body' => $response, 'data' => $data];
}

function rd_latest_reward_time(PDO $pdo, int $userId): ?int
{
    $stmt = $pdo->prepare("SELECT COALESCE(rewarded_at,completed_at,created_at) FROM reward_drop_runs WHERE user_id=? AND status IN ('qualified','issuing','queued','delivered') ORDER BY id DESC LIMIT 1");
    $stmt->execute([$userId]);
    $value = $stmt->fetchColumn();
    return is_string($value) && $value !== '' ? strtotime($value) : null;
}

function rd_cooldown(PDO $pdo, int $userId, array $config): array
{
    $last = rd_latest_reward_time($pdo, $userId);
    $seconds = (int)$config['cooldown_hours'] * 3600;
    $availableAt = $last !== null ? $last + $seconds : time();
    return [
        'eligible' => $last === null || $availableAt <= time(),
        'available_at' => gmdate('c', $availableAt),
        'remaining_seconds' => max(0, $availableAt - time()),
    ];
}

function rd_run_payload(array $row): array
{
    return [
        'run_id' => (string)$row['public_id'],
        'score' => (int)$row['score'],
        'target_score' => (int)$row['target_score'],
        'status' => (string)$row['status'],
        'reward_id' => $row['reward_public_id'] !== null ? (string)$row['reward_public_id'] : null,
        'api_status' => $row['api_status'] !== null ? (string)$row['api_status'] : null,
        'message' => $row['error_message'] !== null ? (string)$row['error_message'] : null,
        'inbox_url' => '/inbox.php',
        'started_at' => (string)$row['started_at'],
        'completed_at' => $row['completed_at'],
        'rewarded_at' => $row['rewarded_at'],
        'webhook_received_at' => $row['webhook_received_at'],
    ];
}

function rd_find_run(PDO $pdo, string $runId, int $userId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM reward_drop_runs WHERE public_id=? AND user_id=? LIMIT 1');
    $stmt->execute([$runId, $userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function rd_server_header(string $name): string
{
    $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
    return trim((string)($_SERVER[$key] ?? ''));
}
