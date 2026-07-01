<?php
declare(strict_types=1);

function mg_agent_admin_limit_clean(mixed $value, int $max = 240): string
{
    $text = preg_replace('/\s+/u', ' ', trim((string)$value)) ?? '';
    return mb_substr($text, 0, $max);
}

function mg_agent_admin_limit_int(mixed $value, ?int $fallback = null, int $max = 1000000): ?int
{
    if ($value === null || $value === '') return $fallback;
    $n = filter_var($value, FILTER_VALIDATE_INT);
    if ($n === false) return $fallback;
    return max(0, min($max, (int)$n));
}

function mg_agent_admin_limit_provider(PDO $pdo, string $providerKey = 'anthropic'): array
{
    $stmt = $pdo->prepare('SELECT * FROM ai_providers WHERE provider_key=? LIMIT 1');
    $stmt->execute([$providerKey]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) mg_fail('AI provider is not configured.', 422);
    return $row;
}

function mg_agent_admin_limit_latest(PDO $pdo, int $merchantId, string $providerKey = 'anthropic'): array
{
    try {
        $stmt = $pdo->prepare("SELECT event_context_json FROM campaign_events WHERE merchant_user_id=? AND event_type='merchant.ai_user_limits.updated' ORDER BY id DESC LIMIT 1");
        $stmt->execute([$merchantId]);
        $ctx = json_decode((string)($stmt->fetchColumn() ?: ''), true);
        if (!is_array($ctx)) return [];
        $ctxProvider = (string)($ctx['provider_key'] ?? '');
        if ($ctxProvider !== '' && $ctxProvider !== $providerKey) return [];
        return $ctx;
    } catch (Throwable) {
        return [];
    }
}

function mg_agent_admin_limit_public(PDO $pdo, int $merchantId, string $providerKey = 'anthropic'): array
{
    $ctx = mg_agent_admin_limit_latest($pdo, $merchantId, $providerKey);
    return [
        'provider_key' => $providerKey,
        'enabled' => array_key_exists('enabled', $ctx) ? (bool)$ctx['enabled'] : true,
        'requests_per_hour' => mg_agent_admin_limit_int($ctx['requests_per_hour'] ?? null, null),
        'requests_per_day' => mg_agent_admin_limit_int($ctx['requests_per_day'] ?? null, null),
        'note' => mg_agent_admin_limit_clean($ctx['note'] ?? '', 240),
        'updated_at' => (string)($ctx['updated_at'] ?? ''),
        'updated_by_user_id' => isset($ctx['updated_by_user_id']) ? (int)$ctx['updated_by_user_id'] : null,
    ];
}

function mg_agent_admin_limit_save(PDO $pdo, int $merchantId, string $providerKey, array $input, int $adminUserId): array
{
    $provider = mg_agent_admin_limit_provider($pdo, $providerKey);
    $ctx = [
        'provider_key' => (string)$provider['provider_key'],
        'provider_id' => (int)$provider['id'],
        'enabled' => !array_key_exists('enabled', $input) || !empty($input['enabled']),
        'requests_per_hour' => mg_agent_admin_limit_int($input['requests_per_hour'] ?? null, null),
        'requests_per_day' => mg_agent_admin_limit_int($input['requests_per_day'] ?? null, null),
        'note' => mg_agent_admin_limit_clean($input['note'] ?? '', 240),
        'updated_by_user_id' => $adminUserId,
        'updated_at' => date('c'),
    ];
    $publicId = function_exists('mg_public_uuid') ? mg_public_uuid() : ('ai_limit_' . bin2hex(random_bytes(10)));
    $stmt = $pdo->prepare('INSERT INTO campaign_events (public_id,merchant_user_id,campaign_id,contact_id,event_type,event_context_json,created_at) VALUES (?,?,?,?,?,?,NOW())');
    $stmt->execute([$publicId, $merchantId, null, null, 'merchant.ai_user_limits.updated', json_encode($ctx, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]);
    return mg_agent_admin_limit_public($pdo, $merchantId, (string)$provider['provider_key']);
}

function mg_agent_admin_limit_count(PDO $pdo, int $providerId, int $merchantId, DateTimeImmutable $since): int
{
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(request_units),0) FROM ai_usage_events WHERE provider_id=? AND user_id=? AND request_status IN ('allowed','completed') AND created_at>=?");
    $stmt->execute([$providerId, $merchantId, $since->format('Y-m-d H:i:s')]);
    return (int)$stmt->fetchColumn();
}

function mg_agent_admin_limit_block(PDO $pdo, int $providerId, ?int $modelId, int $merchantId, string $scope, array $meta): void
{
    try {
        $stmt = $pdo->prepare('INSERT INTO ai_usage_events (provider_id, model_id, user_id, agent_id, request_status, block_scope, request_units, metadata_json, created_at) VALUES (?, ?, ?, NULL, ?, ?, 1, ?, NOW())');
        $stmt->execute([$providerId, $modelId, $merchantId, 'blocked', $scope, json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]);
    } catch (Throwable) {}
}

function mg_agent_admin_limit_enforce(PDO $pdo, array $provider, ?array $model, int $merchantId): void
{
    $providerKey = (string)($provider['provider_key'] ?? 'anthropic');
    $limits = mg_agent_admin_limit_public($pdo, $merchantId, $providerKey);
    $providerId = (int)($provider['id'] ?? 0);
    $modelId = $model ? (int)($model['id'] ?? 0) : null;
    if (!$limits['enabled']) {
        mg_agent_admin_limit_block($pdo, $providerId, $modelId, $merchantId, 'user_admin_disabled', ['source' => 'admin_merchant_ai_limits']);
        mg_fail('AI access is disabled for this merchant by an administrator.', 429, ['scope' => 'user_admin_disabled']);
    }
    $now = new DateTimeImmutable('now');
    $hourLimit = (int)($limits['requests_per_hour'] ?? 0);
    if ($hourLimit > 0) {
        $used = mg_agent_admin_limit_count($pdo, $providerId, $merchantId, $now->modify('-1 hour'));
        if ($used >= $hourLimit) {
            mg_agent_admin_limit_block($pdo, $providerId, $modelId, $merchantId, 'user_admin_hour', ['used' => $used, 'limit' => $hourLimit]);
            mg_fail('AI request limit reached for this merchant.', 429, ['scope' => 'user_admin_hour', 'limit' => $hourLimit, 'used' => $used]);
        }
    }
    $dayLimit = (int)($limits['requests_per_day'] ?? 0);
    if ($dayLimit > 0) {
        $used = mg_agent_admin_limit_count($pdo, $providerId, $merchantId, $now->modify('-1 day'));
        if ($used >= $dayLimit) {
            mg_agent_admin_limit_block($pdo, $providerId, $modelId, $merchantId, 'user_admin_day', ['used' => $used, 'limit' => $dayLimit]);
            mg_fail('AI request limit reached for this merchant.', 429, ['scope' => 'user_admin_day', 'limit' => $dayLimit, 'used' => $used]);
        }
    }
}
