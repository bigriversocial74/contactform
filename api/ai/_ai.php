<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/agents/_agent.php';

function mg_ai_public_id(string $prefix = 'ai'): string
{
    return $prefix . '_' . bin2hex(random_bytes(12));
}

function mg_ai_env_value(string $envVar): string
{
    return trim((string)(getenv($envVar) ?: ''));
}

function mg_ai_env_configured(string $envVar): bool
{
    return mg_ai_env_value($envVar) !== '';
}

function mg_ai_key_diagnostic(string $envVar): array
{
    $raw = (string)(getenv($envVar) ?: '');
    $key = trim($raw);
    $warnings = [];
    if ($key === '') {
        return [
            'configured' => false,
            'length' => 0,
            'prefix' => '',
            'suffix' => '',
            'fingerprint' => '',
            'warnings' => ['No key is loaded in this PHP request.'],
        ];
    }
    if ($raw !== $key) $warnings[] = 'The loaded key had leading or trailing whitespace; the app trims it before sending.';
    if (preg_match('/\s/', $key) === 1) $warnings[] = 'The loaded key contains whitespace inside the value.';
    if (stripos($key, 'Bearer ') === 0) $warnings[] = 'Remove the Bearer prefix. Anthropic x-api-key expects only the raw key.';
    if (str_contains($key, 'PASTE_') || str_contains($key, 'YOUR_')) $warnings[] = 'The loaded key still looks like a placeholder.';
    if (str_contains($key, '<') || str_contains($key, '>') || str_contains($key, '&')) $warnings[] = 'The loaded key may contain copied HTML characters.';
    if (str_starts_with($key, 'sk-ant-') === false) $warnings[] = 'The loaded key does not start with the expected Anthropic sk-ant- prefix.';
    if (strlen($key) < 30) $warnings[] = 'The loaded key is unusually short.';
    return [
        'configured' => true,
        'length' => strlen($key),
        'prefix' => substr($key, 0, 12),
        'suffix' => substr($key, -4),
        'fingerprint' => substr(hash('sha256', $key), 0, 12),
        'warnings' => $warnings,
    ];
}

function mg_ai_limit_int(mixed $value, int $fallback, int $max = 1000000): int
{
    $n = is_numeric($value) ? (int)$value : $fallback;
    return max(0, min($max, $n));
}

function mg_ai_provider_rows(PDO $pdo): array
{
    $stmt = $pdo->query('SELECT * FROM ai_providers ORDER BY display_name, provider_key');
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function mg_ai_models_for_provider(PDO $pdo, int $providerId): array
{
    $stmt = $pdo->prepare('SELECT * FROM ai_models WHERE provider_id=? ORDER BY sort_order, display_name, model_key');
    $stmt->execute([$providerId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function mg_ai_public_model(array $model): array
{
    return [
        'id' => (string)$model['public_id'],
        'model_key' => (string)$model['model_key'],
        'display_name' => (string)$model['display_name'],
        'enabled' => (bool)$model['enabled'],
        'is_default' => (bool)$model['is_default'],
        'sort_order' => (int)$model['sort_order'],
        'max_input_tokens' => $model['max_input_tokens'] !== null ? (int)$model['max_input_tokens'] : null,
        'max_output_tokens' => $model['max_output_tokens'] !== null ? (int)$model['max_output_tokens'] : null,
    ];
}

function mg_ai_public_provider(PDO $pdo, array $provider): array
{
    $models = array_map('mg_ai_public_model', mg_ai_models_for_provider($pdo, (int)$provider['id']));
    $envVar = (string)$provider['env_var_name'];
    return [
        'id' => (string)$provider['public_id'],
        'provider_key' => (string)$provider['provider_key'],
        'display_name' => (string)$provider['display_name'],
        'env_var_name' => $envVar,
        'configured' => mg_ai_env_configured($envVar),
        'key_diagnostic' => (string)$provider['provider_key'] === 'anthropic' ? mg_ai_key_diagnostic($envVar) : null,
        'enabled' => (bool)$provider['enabled'],
        'rate_limit_per_minute' => (int)$provider['rate_limit_per_minute'],
        'rate_limit_per_hour' => (int)$provider['rate_limit_per_hour'],
        'rate_limit_per_day' => (int)$provider['rate_limit_per_day'],
        'user_rate_limit_per_hour' => (int)$provider['user_rate_limit_per_hour'],
        'user_rate_limit_per_day' => (int)$provider['user_rate_limit_per_day'],
        'agent_rate_limit_per_hour' => (int)$provider['agent_rate_limit_per_hour'],
        'agent_rate_limit_per_day' => (int)$provider['agent_rate_limit_per_day'],
        'models' => $models,
    ];
}

function mg_ai_public_settings(PDO $pdo): array
{
    $providers = [];
    foreach (mg_ai_provider_rows($pdo) as $provider) {
        $providers[] = mg_ai_public_provider($pdo, $provider);
    }
    return ['providers' => $providers];
}

function mg_ai_available_models(PDO $pdo): array
{
    $stmt = $pdo->query("SELECT m.*, p.provider_key, p.display_name provider_name, p.env_var_name, p.enabled provider_enabled
        FROM ai_models m
        INNER JOIN ai_providers p ON p.id=m.provider_id
        WHERE m.enabled=1 AND p.enabled=1
        ORDER BY p.display_name, m.sort_order, m.display_name");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $models = [];
    foreach ($rows as $row) {
        if (!mg_ai_env_configured((string)$row['env_var_name'])) {
            continue;
        }
        $models[] = [
            'id' => (string)$row['public_id'],
            'provider_key' => (string)$row['provider_key'],
            'provider_name' => (string)$row['provider_name'],
            'model_key' => (string)$row['model_key'],
            'display_name' => (string)$row['display_name'],
            'is_default' => (bool)$row['is_default'],
        ];
    }
    return $models;
}

function mg_ai_find_model(PDO $pdo, string $modelPublicId): ?array
{
    $stmt = $pdo->prepare('SELECT m.*, p.provider_key, p.display_name provider_name, p.env_var_name, p.enabled provider_enabled FROM ai_models m INNER JOIN ai_providers p ON p.id=m.provider_id WHERE m.public_id=? LIMIT 1');
    $stmt->execute([$modelPublicId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
}

function mg_ai_agent_setting(PDO $pdo, int $agentId): ?array
{
    $stmt = $pdo->prepare('SELECT aas.*, m.public_id model_public_id, m.model_key, m.display_name model_name, p.provider_key, p.display_name provider_name FROM agent_ai_settings aas INNER JOIN ai_models m ON m.id=aas.model_id INNER JOIN ai_providers p ON p.id=aas.provider_id WHERE aas.agent_id=? LIMIT 1');
    $stmt->execute([$agentId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
}

function mg_ai_public_agent_setting(?array $setting): ?array
{
    if (!$setting) return null;
    return [
        'model_id' => (string)$setting['model_public_id'],
        'provider_key' => (string)$setting['provider_key'],
        'provider_name' => (string)$setting['provider_name'],
        'model_key' => (string)$setting['model_key'],
        'model_name' => (string)$setting['model_name'],
        'rate_limit_per_hour' => $setting['rate_limit_per_hour'] !== null ? (int)$setting['rate_limit_per_hour'] : null,
        'rate_limit_per_day' => $setting['rate_limit_per_day'] !== null ? (int)$setting['rate_limit_per_day'] : null,
    ];
}

function mg_ai_set_agent_model(PDO $pdo, array $agent, string $modelPublicId, int $userId): array
{
    $model = mg_ai_find_model($pdo, $modelPublicId);
    if (!$model || !(bool)$model['enabled'] || !(bool)$model['provider_enabled']) {
        mg_fail('Choose an enabled AI model.', 422);
    }
    if (!mg_ai_env_configured((string)$model['env_var_name'])) {
        mg_fail('The selected AI provider is not configured on the server.', 422);
    }
    $existing = mg_ai_agent_setting($pdo, (int)$agent['id']);
    if ($existing) {
        $stmt = $pdo->prepare('UPDATE agent_ai_settings SET provider_id=?, model_id=?, updated_by_user_id=?, updated_at=NOW() WHERE id=?');
        $stmt->execute([(int)$model['provider_id'], (int)$model['id'], $userId, (int)$existing['id']]);
    } else {
        $stmt = $pdo->prepare('INSERT INTO agent_ai_settings (agent_id, provider_id, model_id, updated_by_user_id, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())');
        $stmt->execute([(int)$agent['id'], (int)$model['provider_id'], (int)$model['id'], $userId]);
    }
    $setting = mg_ai_agent_setting($pdo, (int)$agent['id']);
    return $setting ?: [];
}

function mg_ai_count_events(PDO $pdo, string $scopeSql, array $params, DateTimeImmutable $since): int
{
    $stmt = $pdo->prepare('SELECT COALESCE(SUM(request_units),0) FROM ai_usage_events WHERE request_status IN (\'allowed\',\'completed\') AND created_at>=? AND ' . $scopeSql);
    $stmt->execute(array_merge([$since->format('Y-m-d H:i:s')], $params));
    return (int)$stmt->fetchColumn();
}

function mg_ai_insert_usage_event(PDO $pdo, int $providerId, ?int $modelId, ?int $userId, ?int $agentId, string $status, ?string $blockScope = null, array $metadata = []): void
{
    $stmt = $pdo->prepare('INSERT INTO ai_usage_events (provider_id, model_id, user_id, agent_id, request_status, block_scope, request_units, metadata_json, created_at) VALUES (?, ?, ?, ?, ?, ?, 1, ?, NOW())');
    $stmt->execute([$providerId, $modelId, $userId, $agentId, $status, $blockScope, $metadata ? json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null]);
}

function mg_ai_enforce_rate_limits(PDO $pdo, array $provider, ?array $model, ?int $userId, ?int $agentId): void
{
    $now = new DateTimeImmutable('now');
    $providerId = (int)$provider['id'];
    $modelId = $model ? (int)$model['id'] : null;
    $checks = [
        ['scope' => 'global', 'limit' => (int)$provider['rate_limit_per_minute'], 'since' => $now->modify('-1 minute'), 'sql' => 'provider_id=?', 'params' => [$providerId]],
        ['scope' => 'global', 'limit' => (int)$provider['rate_limit_per_hour'], 'since' => $now->modify('-1 hour'), 'sql' => 'provider_id=?', 'params' => [$providerId]],
        ['scope' => 'global', 'limit' => (int)$provider['rate_limit_per_day'], 'since' => $now->modify('-1 day'), 'sql' => 'provider_id=?', 'params' => [$providerId]],
    ];
    if ($userId) {
        $checks[] = ['scope' => 'user', 'limit' => (int)$provider['user_rate_limit_per_hour'], 'since' => $now->modify('-1 hour'), 'sql' => 'provider_id=? AND user_id=?', 'params' => [$providerId, $userId]];
        $checks[] = ['scope' => 'user', 'limit' => (int)$provider['user_rate_limit_per_day'], 'since' => $now->modify('-1 day'), 'sql' => 'provider_id=? AND user_id=?', 'params' => [$providerId, $userId]];
    }
    if ($userId && $agentId) {
        $checks[] = ['scope' => 'agent', 'limit' => (int)$provider['agent_rate_limit_per_hour'], 'since' => $now->modify('-1 hour'), 'sql' => 'provider_id=? AND user_id=? AND agent_id=?', 'params' => [$providerId, $userId, $agentId]];
        $checks[] = ['scope' => 'agent', 'limit' => (int)$provider['agent_rate_limit_per_day'], 'since' => $now->modify('-1 day'), 'sql' => 'provider_id=? AND user_id=? AND agent_id=?', 'params' => [$providerId, $userId, $agentId]];
    }
    foreach ($checks as $check) {
        if ((int)$check['limit'] <= 0) continue;
        $used = mg_ai_count_events($pdo, (string)$check['sql'], $check['params'], $check['since']);
        if ($used >= (int)$check['limit']) {
            mg_ai_insert_usage_event($pdo, $providerId, $modelId, $userId, $agentId, 'blocked', (string)$check['scope'], ['used' => $used, 'limit' => (int)$check['limit']]);
            mg_fail('AI rate limit reached for ' . (string)$check['scope'] . ' scope.', 429, ['scope' => $check['scope'], 'limit' => (int)$check['limit'], 'used' => $used]);
        }
    }
    mg_ai_insert_usage_event($pdo, $providerId, $modelId, $userId, $agentId, 'allowed', null, ['source' => 'preflight']);
}