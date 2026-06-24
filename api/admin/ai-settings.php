<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/ai/_ai.php';

$user = mg_require_permission('admin.settings.manage');
$userId = (int)$user['id'];
$pdo = mg_db();

function mg_admin_ai_provider(PDO $pdo, string $key): array
{
    $stmt = $pdo->prepare('SELECT * FROM ai_providers WHERE provider_key = ? LIMIT 1');
    $stmt->execute([$key]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        throw new InvalidArgumentException('Unknown AI provider.');
    }
    return $row;
}

function mg_admin_ai_provider_key(mixed $value): string
{
    $key = strtolower(trim((string)$value));
    if (preg_match('/^[a-z0-9_-]{2,80}$/', $key) !== 1) {
        throw new InvalidArgumentException('Invalid provider key.');
    }
    return $key;
}

function mg_admin_ai_model_key(mixed $value): string
{
    $key = trim((string)$value);
    if ($key === '' || strlen($key) > 120 || preg_match('/^[A-Za-z0-9._:\/-]+$/', $key) !== 1) {
        throw new InvalidArgumentException('Invalid AI model key.');
    }
    return $key;
}

function mg_admin_ai_text(mixed $value, int $maxLength, bool $required = false): string
{
    $text = preg_replace('/\s+/u', ' ', trim((string)$value)) ?? '';
    if (($required && $text === '') || mb_strlen($text) > $maxLength) {
        throw new InvalidArgumentException('Invalid AI provider settings input.');
    }
    return $text;
}

function mg_admin_ai_save_provider(PDO $pdo, array $input): void
{
    $key = mg_admin_ai_provider_key($input['provider_key'] ?? '');
    $provider = mg_admin_ai_provider($pdo, $key);
    $models = is_array($input['models'] ?? null) ? array_values($input['models']) : [];
    if (count($models) > 100) {
        throw new InvalidArgumentException('Too many AI models in one request.');
    }

    $pdo->prepare(
        'UPDATE ai_providers
         SET enabled = ?,
             rate_limit_per_minute = ?,
             rate_limit_per_hour = ?,
             rate_limit_per_day = ?,
             user_rate_limit_per_hour = ?,
             user_rate_limit_per_day = ?,
             agent_rate_limit_per_hour = ?,
             agent_rate_limit_per_day = ?,
             updated_at = NOW()
         WHERE id = ?'
    )->execute([
        !empty($input['enabled']) ? 1 : 0,
        mg_ai_limit_int($input['rate_limit_per_minute'] ?? null, (int)$provider['rate_limit_per_minute']),
        mg_ai_limit_int($input['rate_limit_per_hour'] ?? null, (int)$provider['rate_limit_per_hour']),
        mg_ai_limit_int($input['rate_limit_per_day'] ?? null, (int)$provider['rate_limit_per_day']),
        mg_ai_limit_int($input['user_rate_limit_per_hour'] ?? null, (int)$provider['user_rate_limit_per_hour']),
        mg_ai_limit_int($input['user_rate_limit_per_day'] ?? null, (int)$provider['user_rate_limit_per_day']),
        mg_ai_limit_int($input['agent_rate_limit_per_hour'] ?? null, (int)$provider['agent_rate_limit_per_hour']),
        mg_ai_limit_int($input['agent_rate_limit_per_day'] ?? null, (int)$provider['agent_rate_limit_per_day']),
        (int)$provider['id'],
    ]);

    $default = trim((string)($input['default_model_key'] ?? ''));
    $modelKeys = [];
    foreach ($models as $model) {
        if (!is_array($model)) {
            throw new InvalidArgumentException('Invalid AI model payload.');
        }
        $modelKeys[] = mg_admin_ai_model_key($model['model_key'] ?? '');
    }
    if ($default !== '' && !in_array($default, $modelKeys, true)) {
        throw new InvalidArgumentException('Default AI model must be included in the provider model list.');
    }
    if ($default !== '') {
        $pdo->prepare('UPDATE ai_models SET is_default = 0 WHERE provider_id = ?')->execute([(int)$provider['id']]);
    }

    foreach ($models as $model) {
        $modelKey = mg_admin_ai_model_key($model['model_key'] ?? '');
        $display = mg_admin_ai_text($model['display_name'] ?? $modelKey, 160, false);
        $enabled = !empty($model['enabled']) ? 1 : 0;
        $isDefault = $default !== '' ? ($modelKey === $default ? 1 : 0) : (!empty($model['is_default']) ? 1 : 0);
        $sort = mg_ai_limit_int($model['sort_order'] ?? 100, 100, 100000);

        $lookup = $pdo->prepare('SELECT id FROM ai_models WHERE provider_id = ? AND model_key = ? LIMIT 1');
        $lookup->execute([(int)$provider['id'], $modelKey]);
        $id = $lookup->fetchColumn();
        if ($id) {
            $pdo->prepare(
                'UPDATE ai_models
                 SET display_name = ?, enabled = ?, is_default = ?, sort_order = ?, updated_at = NOW()
                 WHERE id = ?'
            )->execute([$display !== '' ? $display : $modelKey, $enabled, $isDefault, $sort, (int)$id]);
        } else {
            $pdo->prepare(
                'INSERT INTO ai_models (public_id, provider_id, model_key, display_name, enabled, is_default, sort_order, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
            )->execute([mg_public_uuid(), (int)$provider['id'], $modelKey, $display !== '' ? $display : $modelKey, $enabled, $isDefault, $sort]);
        }
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    mg_rate_limit('admin.ai_settings.read', 'user:' . $userId, 120, 60);
    header('Cache-Control: private, no-store, max-age=0');
    header('Vary: Cookie, Authorization');
    mg_ok(mg_ai_public_settings($pdo));
}

mg_require_method('POST');
mg_rate_limit('admin.ai_settings.write', 'user:' . $userId, 30, 300);
$input = mg_input();
mg_require_csrf_for_write($input);
$providers = is_array($input['providers'] ?? null) ? array_values($input['providers']) : [];
if (count($providers) > 20) {
    mg_fail('Too many AI providers in one request.', 422);
}

try {
    $pdo->beginTransaction();
    foreach ($providers as $provider) {
        if (!is_array($provider)) {
            throw new InvalidArgumentException('Invalid AI provider payload.');
        }
        mg_admin_ai_save_provider($pdo, $provider);
    }
    $pdo->commit();

    mg_audit('admin.ai_settings_updated', 'ai_settings', [
        'provider_count' => count($providers),
    ], $userId);
    mg_security_log('info', 'admin.ai_settings.updated', 'AI provider settings updated.', [
        'provider_count' => count($providers),
    ], $userId);

    header('Cache-Control: private, no-store, max-age=0');
    header('Vary: Cookie, Authorization');
    mg_ok(mg_ai_public_settings($pdo), 'AI provider settings saved.');
} catch (InvalidArgumentException $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    mg_security_log('warning', 'admin.ai_settings.rejected', 'AI provider settings rejected.', [
        'reason' => $error->getMessage(),
    ], $userId);
    mg_fail($error->getMessage(), 422);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    mg_security_log('error', 'admin.ai_settings.failed', 'AI provider settings save failed.', [
        'exception_class' => $error::class,
    ], $userId);
    mg_fail('Unable to save AI provider settings right now.', 500);
}
