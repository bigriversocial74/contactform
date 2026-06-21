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
    $pdo->prepare('UPDATE merchant_api_keys SET last_used_at=NOW(),updated_at=NOW() WHERE id=?')->execute([(int) $row['id']]);
    return ['pdo'=>$pdo,'key'=>$row,'merchant_user_id'=>(int)$row['merchant_user_id'],'app_id'=>(int)$row['app_id'],'app_public_id'=>(string)$row['app_public_id'],'source_connection_id'=>$row['distribution_source_connection_id'] !== null ? (int)$row['distribution_source_connection_id'] : null,'default_program_id'=>$row['default_program_id'] !== null ? (int)$row['default_program_id'] : null,'scopes'=>$scopes];
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
