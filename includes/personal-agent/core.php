<?php
declare(strict_types=1);

function mg_personal_agent_text(mixed $value, int $limit = 1000): string
{
    $text = trim((string) $value);
    $text = preg_replace('/\s+/', ' ', $text) ?? $text;
    return mb_substr($text, 0, $limit);
}

function mg_personal_agent_nullable_text(mixed $value, int $limit = 1000): ?string
{
    $text = mg_personal_agent_text($value, $limit);
    return $text === '' ? null : $text;
}

function mg_personal_agent_json(mixed $value): array
{
    if (is_array($value)) return $value;
    if (!is_string($value) || trim($value) === '') return [];
    $decoded = json_decode($value, true);
    return is_array($decoded) ? $decoded : [];
}

function mg_personal_agent_json_encode(array $value): string
{
    $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($json)) {
        throw new RuntimeException('Unable to encode personal agent data.');
    }
    return $json;
}

function mg_personal_agent_currency(mixed $value): string
{
    $currency = strtoupper(mg_personal_agent_text($value ?: 'USD', 3));
    return preg_match('/^[A-Z]{3}$/', $currency) === 1 ? $currency : 'USD';
}

function mg_personal_agent_decimal(mixed $value): ?float
{
    if ($value === null || $value === '') return null;
    if (!is_numeric($value)) throw new InvalidArgumentException('Enter a valid budget amount.');
    $number = round((float) $value, 2);
    if ($number < 0 || $number > 1000000) throw new InvalidArgumentException('Budget amount is outside the allowed range.');
    return $number;
}

function mg_personal_agent_date(mixed $value): ?string
{
    $value = mg_personal_agent_text($value, 10);
    if ($value === '') return null;
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'));
    if (!$date || $date->format('Y-m-d') !== $value) {
        throw new InvalidArgumentException('Enter a valid date.');
    }
    return $value;
}

function mg_personal_agent_datetime(mixed $value): string
{
    $value = trim((string) $value);
    if ($value === '') throw new InvalidArgumentException('Reminder time is required.');
    try {
        $date = new DateTimeImmutable($value);
    } catch (Throwable) {
        throw new InvalidArgumentException('Enter a valid reminder date and time.');
    }
    return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
}

function mg_personal_agent_table_exists(PDO $pdo, string $table): bool
{
    try {
        $stmt = $pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=? LIMIT 1');
        $stmt->execute([$table]);
        return (bool) $stmt->fetchColumn();
    } catch (Throwable) {
        return false;
    }
}

function mg_personal_agent_require_schema(PDO $pdo): void
{
    foreach (['user_agent_settings','user_gifting_plans','user_gifting_reminders','user_agent_memory','user_agent_threads','user_agent_messages'] as $table) {
        if (!mg_personal_agent_table_exists($pdo, $table)) {
            throw new RuntimeException('Personal Gifting Agent Phase 2 database migration is required.');
        }
    }
}

function mg_personal_agent_settings(PDO $pdo, int $userId): array
{
    mg_personal_agent_require_schema($pdo);
    $pdo->prepare('INSERT IGNORE INTO user_agent_settings (user_id) VALUES (?)')->execute([$userId]);
    $stmt = $pdo->prepare("SELECT s.*,m.public_id model_public_id,m.model_key,m.display_name model_name,p.provider_key,p.display_name provider_name
        FROM user_agent_settings s
        LEFT JOIN ai_models m ON m.id=s.preferred_model_id
        LEFT JOIN ai_providers p ON p.id=m.provider_id
        WHERE s.user_id=? LIMIT 1");
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    return [
        'preferred_model_id' => (string) ($row['model_public_id'] ?? ''),
        'model_key' => (string) ($row['model_key'] ?? ''),
        'model_name' => (string) ($row['model_name'] ?? ''),
        'provider_name' => (string) ($row['provider_name'] ?? ''),
        'default_currency' => mg_personal_agent_currency($row['default_currency'] ?? 'USD'),
        'default_budget_min' => $row['default_budget_min'] !== null ? (float) $row['default_budget_min'] : null,
        'default_budget_max' => $row['default_budget_max'] !== null ? (float) $row['default_budget_max'] : null,
        'approval_mode' => in_array((string) ($row['approval_mode'] ?? ''), ['advisory','draft_only'], true) ? (string) $row['approval_mode'] : 'draft_only',
        'suggestion_horizon_days' => max(7, min(365, (int) ($row['suggestion_horizon_days'] ?? 45))),
        'enable_suggestions' => (bool) ($row['enable_suggestions'] ?? true),
        'enable_date_brief' => (bool) ($row['enable_date_brief'] ?? true),
    ];
}

function mg_personal_agent_available_models(PDO $pdo): array
{
    $rows = $pdo->query("SELECT m.public_id,m.model_key,m.display_name,m.is_default,p.display_name provider_name,p.env_var_name
        FROM ai_models m
        INNER JOIN ai_providers p ON p.id=m.provider_id
        WHERE m.enabled=1 AND p.enabled=1 AND p.provider_key='anthropic'
          AND LOWER(m.model_key) NOT LIKE '%opus%'
          AND LOWER(m.model_key) NOT LIKE '%fable%'
        ORDER BY m.is_default DESC,m.sort_order,m.display_name")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $out = [];
    foreach ($rows as $row) {
        if (!mg_ai_env_configured((string) $row['env_var_name'])) continue;
        $out[] = [
            'id' => (string) $row['public_id'],
            'model_key' => (string) $row['model_key'],
            'name' => (string) $row['display_name'],
            'provider' => (string) $row['provider_name'],
            'is_default' => (bool) $row['is_default'],
        ];
    }
    return $out;
}

function mg_personal_agent_update_settings(PDO $pdo, int $userId, array $input): array
{
    mg_personal_agent_require_schema($pdo);
    $modelId = null;
    $modelPublicId = mg_personal_agent_text($input['preferred_model_id'] ?? '', 80);
    if ($modelPublicId !== '') {
        $stmt = $pdo->prepare("SELECT m.id,p.env_var_name FROM ai_models m INNER JOIN ai_providers p ON p.id=m.provider_id
            WHERE m.public_id=? AND m.enabled=1 AND p.enabled=1 AND p.provider_key='anthropic' LIMIT 1");
        $stmt->execute([$modelPublicId]);
        $modelRow = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$modelRow || !mg_ai_env_configured((string) $modelRow['env_var_name'])) {
            throw new InvalidArgumentException('Choose an available configured Claude model.');
        }
        $modelId = (int) $modelRow['id'];
    }
    $currency = mg_personal_agent_currency($input['default_currency'] ?? 'USD');
    $budgetMin = mg_personal_agent_decimal($input['default_budget_min'] ?? null);
    $budgetMax = mg_personal_agent_decimal($input['default_budget_max'] ?? null);
    if ($budgetMin !== null && $budgetMax !== null && $budgetMin > $budgetMax) {
        throw new InvalidArgumentException('Minimum budget cannot exceed maximum budget.');
    }
    $approvalMode = mg_personal_agent_text($input['approval_mode'] ?? 'draft_only', 30);
    if (!in_array($approvalMode, ['advisory','draft_only'], true)) $approvalMode = 'draft_only';
    $horizon = max(7, min(365, (int) ($input['suggestion_horizon_days'] ?? 45)));
    $suggestions = filter_var($input['enable_suggestions'] ?? true, FILTER_VALIDATE_BOOLEAN);
    $dateBrief = filter_var($input['enable_date_brief'] ?? true, FILTER_VALIDATE_BOOLEAN);
    $stmt = $pdo->prepare("INSERT INTO user_agent_settings
        (user_id,preferred_model_id,default_currency,default_budget_min,default_budget_max,approval_mode,suggestion_horizon_days,enable_suggestions,enable_date_brief,created_at,updated_at)
        VALUES (?,?,?,?,?,?,?,?,?,NOW(),NOW())
        ON DUPLICATE KEY UPDATE preferred_model_id=VALUES(preferred_model_id),default_currency=VALUES(default_currency),
        default_budget_min=VALUES(default_budget_min),default_budget_max=VALUES(default_budget_max),approval_mode=VALUES(approval_mode),
        suggestion_horizon_days=VALUES(suggestion_horizon_days),enable_suggestions=VALUES(enable_suggestions),
        enable_date_brief=VALUES(enable_date_brief),updated_at=NOW()");
    $stmt->execute([$userId,$modelId,$currency,$budgetMin,$budgetMax,$approvalMode,$horizon,$suggestions ? 1 : 0,$dateBrief ? 1 : 0]);
    mg_audit('user_agent.settings_updated', 'user_agent_settings', ['approval_mode'=>$approvalMode,'horizon_days'=>$horizon], $userId);
    return mg_personal_agent_settings($pdo, $userId);
}
