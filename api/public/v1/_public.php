<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/distribution/_distribution.php';

function mg_public_request_header(string $name): string
{
    $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
    return trim((string) ($_SERVER[$serverKey] ?? ''));
}

function mg_public_auth_value(): string
{
    $header = mg_public_request_header('Authorization');
    if (stripos($header, 'Bearer ') === 0) return trim(substr($header, 7));
    $fallback = trim((string) ($_GET['access_token'] ?? ''));
    return $fallback;
}

function mg_public_quota_limit(string $envName, int $default): int
{
    $value = getenv($envName);
    if (is_string($value) && ctype_digit($value) && (int)$value > 0) return (int)$value;
    return $default;
}

function mg_public_quota_windows(): array
{
    $now = time();
    $minuteStart = strtotime(gmdate('Y-m-d H:i:00', $now));
    $dayStart = strtotime(gmdate('Y-m-d 00:00:00', $now));
    $monthStart = strtotime(gmdate('Y-m-01 00:00:00', $now));
    return [
        [
            'scope' => 'minute',
            'key' => gmdate('YmdHi', $now),
            'limit' => mg_public_quota_limit('MG_PUBLIC_API_RATE_PER_MINUTE', 60),
            'start' => gmdate('Y-m-d H:i:s', $minuteStart),
            'end' => gmdate('Y-m-d H:i:s', $minuteStart + 60),
            'reset' => $minuteStart + 60,
        ],
        [
            'scope' => 'day',
            'key' => gmdate('Ymd', $now),
            'limit' => mg_public_quota_limit('MG_PUBLIC_API_DAILY_QUOTA', 5000),
            'start' => gmdate('Y-m-d H:i:s', $dayStart),
            'end' => gmdate('Y-m-d H:i:s', $dayStart + 86400),
            'reset' => $dayStart + 86400,
        ],
        [
            'scope' => 'month',
            'key' => gmdate('Ym', $now),
            'limit' => mg_public_quota_limit('MG_PUBLIC_API_MONTHLY_QUOTA', 100000),
            'start' => gmdate('Y-m-d H:i:s', $monthStart),
            'end' => gmdate('Y-m-d H:i:s', strtotime('+1 month', $monthStart)),
            'reset' => strtotime('+1 month', $monthStart),
        ],
    ];
}

function mg_public_quota_headers(array $quota): void
{
    if (headers_sent()) return;
    header('X-RateLimit-Limit: ' . (string)$quota['limit']);
    header('X-RateLimit-Remaining: ' . (string)max(0, (int)$quota['remaining']));
    header('X-RateLimit-Reset: ' . (string)$quota['reset']);
}

function mg_public_enforce_quotas(PDO $pdo, array $context): array
{
    $windows = mg_public_quota_windows();
    $minuteState = null;
    try {
        $pdo->beginTransaction();
        foreach ($windows as $window) {
            $pdo->prepare("INSERT IGNORE INTO public_api_quota_buckets (public_id,merchant_user_id,app_id,api_key_id,bucket_scope,bucket_key,limit_value,used_count,window_start,window_end,created_at,updated_at) VALUES (?,?,?,?,?,?,?,0,?,?,NOW(),NOW())")
                ->execute([
                    mg_distribution_uuid(),
                    (int)$context['merchant_user_id'],
                    (int)$context['app_id'],
                    (int)$context['key']['id'],
                    $window['scope'],
                    $window['key'],
                    (int)$window['limit'],
                    $window['start'],
                    $window['end'],
                ]);
            $stmt = $pdo->prepare("SELECT id,used_count FROM public_api_quota_buckets WHERE api_key_id=? AND bucket_scope=? AND bucket_key=? FOR UPDATE");
            $stmt->execute([(int)$context['key']['id'], $window['scope'], $window['key']]);
            $bucket = $stmt->fetch();
            if (!$bucket) throw new RuntimeException('Quota bucket missing.');
            $used = (int)$bucket['used_count'];
            if ($used >= (int)$window['limit']) {
                $pdo->rollBack();
                $state = $window + ['remaining' => 0, 'used' => $used];
                mg_public_quota_headers($state);
                if (!headers_sent()) header('Retry-After: ' . (string)max(1, (int)$window['reset'] - time()));
                mg_public_log($pdo, $context, 429, 'rate_limited', 'Public API request quota exceeded.');
                mg_fail('Public API request quota exceeded.', 429);
            }
            $pdo->prepare('UPDATE public_api_quota_buckets SET used_count=used_count+1,limit_value=?,updated_at=NOW() WHERE id=?')
                ->execute([(int)$window['limit'], (int)$bucket['id']]);
            $state = $window + ['used' => $used + 1, 'remaining' => (int)$window['limit'] - ($used + 1)];
            if ($window['scope'] === 'minute') $minuteState = $state;
        }
        $pdo->commit();
        if (is_array($minuteState)) mg_public_quota_headers($minuteState);
        return ['minute' => $minuteState];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        mg_public_log($pdo, $context, 500, 'quota_error', $e->getMessage());
        mg_fail('Public API quota check failed.', 500);
    }
}

function mg_public_context(?string $requiredScope = null): array
{
    $value = mg_public_auth_value();
    if ($value === '' || strlen($value) < 24) mg_fail('Missing public API credential.', 401);
    $digest = hash('sha256', $value);
    $pdo = mg_db();
    $stmt = $pdo->prepare("SELECT mak.*,mda.public_id AS app_public_id,mda.name AS app_name,mda.status AS app_status,mda.environment AS app_environment,mda.allowed_origins_json,mda.webhook_url,mda.default_program_id,mda.distribution_source_connection_id,dsc.public_id AS source_public_id,dsc.status AS source_status FROM merchant_api_keys mak INNER JOIN merchant_developer_apps mda ON mda.id=mak.app_id LEFT JOIN distribution_source_connections dsc ON dsc.id=mda.distribution_source_connection_id WHERE mak.key_hash=? AND mak.status='active' LIMIT 1");
    $stmt->execute([$digest]);
    $row = $stmt->fetch();
    if (!$row || (string) $row['app_status'] !== 'active') mg_fail('Invalid public API credential.', 401);
    if (!empty($row['expires_at']) && strtotime((string) $row['expires_at']) < time()) mg_fail('Public API credential expired.', 401);
    $scopes = $row['scopes_json'] ? json_decode((string) $row['scopes_json'], true) : [];
    if (!is_array($scopes)) $scopes = [];
    if ($requiredScope !== null && !in_array($requiredScope, $scopes, true)) mg_fail('Public API credential scope is insufficient.', 403);
    $context = ['pdo'=>$pdo,'key'=>$row,'merchant_user_id'=>(int)$row['merchant_user_id'],'app_id'=>(int)$row['app_id'],'app_public_id'=>(string)$row['app_public_id'],'source_connection_id'=>$row['distribution_source_connection_id'] !== null ? (int)$row['distribution_source_connection_id'] : null,'default_program_id'=>$row['default_program_id'] !== null ? (int)$row['default_program_id'] : null,'scopes'=>$scopes];
    $context['quota'] = mg_public_enforce_quotas($pdo, $context);
    $pdo->prepare('UPDATE merchant_api_keys SET last_used_at=NOW(),updated_at=NOW() WHERE id=?')->execute([(int) $row['id']]);
    return $context;
}

function mg_public_log(PDO $pdo, array $context, int $statusCode, string $responseStatus, ?string $errorMessage = null): void
{
    try {
        $payload = file_get_contents('php://input') ?: '';
        $pdo->prepare("INSERT INTO distribution_api_request_logs (public_id,merchant_user_id,app_id,api_key_id,source_connection_id,request_id,method,endpoint,status_code,response_status,idempotency_key,request_checksum,ip_hash,user_agent_hash,error_message,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())")
            ->execute([
                mg_distribution_uuid(),
                $context['merchant_user_id'] ?? null,
                $context['app_id'] ?? null,
                isset($context['key']['id']) ? (int)$context['key']['id'] : null,
                $context['source_connection_id'] ?? null,
                mg_public_request_header('X-Request-ID') ?: null,
                strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')),
                substr((string)($_SERVER['REQUEST_URI'] ?? ''), 0, 255),
                $statusCode,
                $responseStatus,
                mg_public_request_header('X-Idempotency-Key') ?: null,
                $payload !== '' ? hash('sha256', $payload) : null,
                !empty($_SERVER['REMOTE_ADDR']) ? hash('sha256', (string)$_SERVER['REMOTE_ADDR']) : null,
                !empty($_SERVER['HTTP_USER_AGENT']) ? hash('sha256', (string)$_SERVER['HTTP_USER_AGENT']) : null,
                $errorMessage,
            ]);
    } catch (Throwable $e) {
        // Logging must never block API execution.
    }
}
