<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';

function mg_reward_templates_require_access(bool $manage): array
{
    $user = mg_require_api_user();
    $permission = $manage ? 'merchant.reward_templates.manage' : 'merchant.reward_templates.view';
    $roles = is_array($user['roles'] ?? null) ? $user['roles'] : [];
    $permissions = is_array($user['permissions'] ?? null) ? $user['permissions'] : [];
    if (
        in_array('super_admin', $roles, true)
        || in_array('admin', $roles, true)
        || in_array('merchant', $roles, true)
        || in_array($permission, $permissions, true)
    ) {
        return $user;
    }
    mg_audit('permission_denied', 'security', ['permission' => $permission], (int) $user['id']);
    mg_security_log('warning', 'permission.denied', 'Permission denied.', ['permission' => $permission], (int) $user['id']);
    mg_fail('Merchant reward template access is not enabled for this account.', 403);
}

function mg_reward_template_money_to_cents(mixed $value): int
{
    $raw = trim((string) $value);
    if ($raw === '') return 0;
    if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $raw)) mg_fail('Invalid reward value amount.', 422);
    return (int) round(((float) $raw) * 100);
}

function mg_reward_template_csv_json(mixed $value): ?string
{
    if (is_array($value)) {
        $items = array_values(array_filter(array_map(static fn($v) => trim((string) $v), $value), static fn($v) => $v !== ''));
    } else {
        $items = array_values(array_filter(array_map('trim', explode(',', (string) $value)), static fn($v) => $v !== ''));
    }
    if ($items === []) return null;
    return json_encode($items, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
}

function mg_reward_template_decode(?string $json): array
{
    if ($json === null || trim($json) === '') return [];
    $data = json_decode($json, true);
    return is_array($data) ? array_values($data) : [];
}

function mg_reward_template_row(array $row): array
{
    return [
        'id' => (string) $row['public_id'],
        'title' => (string) $row['title'],
        'description' => (string) ($row['description'] ?? ''),
        'reward_type' => (string) $row['reward_type'],
        'value_type' => (string) $row['value_type'],
        'value_amount_cents' => (int) $row['value_amount_cents'],
        'value_amount' => number_format(((int) $row['value_amount_cents']) / 100, 2, '.', ''),
        'value_percent' => $row['value_percent'] === null ? null : (float) $row['value_percent'],
        'currency' => (string) $row['currency'],
        'redemption_instructions' => (string) ($row['redemption_instructions'] ?? ''),
        'expiration_rule' => (string) $row['expiration_rule'],
        'expiration_days' => $row['expiration_days'] === null ? null : (int) $row['expiration_days'],
        'expires_at' => $row['expires_at'] ?? null,
        'quantity_limit' => $row['quantity_limit'] === null ? null : (int) $row['quantity_limit'],
        'issued_count' => (int) ($row['issued_count'] ?? 0),
        'per_user_limit' => (int) ($row['per_user_limit'] ?? 1),
        'agent_discoverable' => (bool) ((int) ($row['agent_discoverable'] ?? 0)),
        'agent_summary' => (string) ($row['agent_summary'] ?? ''),
        'agent_categories' => mg_reward_template_decode($row['agent_categories_json'] ?? null),
        'agent_use_cases' => mg_reward_template_decode($row['agent_use_cases_json'] ?? null),
        'agent_add_to_wallet_allowed' => (bool) ((int) ($row['agent_add_to_wallet_allowed'] ?? 0)),
        'agent_gift_send_allowed' => (bool) ((int) ($row['agent_gift_send_allowed'] ?? 0)),
        'status' => (string) $row['status'],
        'created_at' => $row['created_at'] ?? null,
        'updated_at' => $row['updated_at'] ?? null,
    ];
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$user = mg_reward_templates_require_access($method !== 'GET');
$merchantId = (int) $user['id'];
$pdo = mg_db();
mg_merchant_ensure_workspace($pdo, $user);

if ($method === 'GET') {
    try {
        $status = trim((string) ($_GET['status'] ?? 'all'));
        $allowedStatus = ['draft','active','paused','archived'];
        $sql = 'SELECT * FROM reward_templates WHERE merchant_user_id = ?';
        $params = [$merchantId];
        if (in_array($status, $allowedStatus, true)) {
            $sql .= ' AND status = ?';
            $params[] = $status;
        }
        $sql .= ' ORDER BY updated_at DESC, id DESC LIMIT 100';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $templates = array_map('mg_reward_template_row', $stmt->fetchAll());
        mg_ok(['templates' => $templates, 'schema_ready' => true]);
    } catch (Throwable $error) {
        mg_security_log('warning', 'merchant.reward_templates.schema_unavailable', 'Reward template schema is unavailable.', [
            'exception_class' => $error::class,
        ], $merchantId);
        mg_ok(['templates' => [], 'schema_ready' => false], 'Reward templates unavailable until the Stage 12 schema is installed.');
    }
}

if ($method !== 'POST') mg_fail('Method not allowed.', 405);
$input = mg_input();
mg_require_csrf_for_write($input);

$templateId = strtolower(trim((string) ($input['template_id'] ?? '')));
$title = trim((string) ($input['title'] ?? ''));
$description = trim((string) ($input['description'] ?? '')) ?: null;
$rewardType = trim((string) ($input['reward_type'] ?? 'custom'));
$status = trim((string) ($input['status'] ?? 'draft'));
$valueType = trim((string) ($input['value_type'] ?? ($rewardType === 'discount' ? 'percent' : 'fixed_amount')));
$valueRaw = trim((string) ($input['value_amount'] ?? $input['value_amount_cents'] ?? ''));
$percentRaw = trim((string) ($input['value_percent'] ?? $valueRaw));
$valueAmountCents = $valueType === 'percent' ? 0 : mg_reward_template_money_to_cents($valueRaw);
$valuePercent = $valueType === 'percent' ? ($percentRaw === '' ? null : (float) $percentRaw) : null;
$currency = strtoupper(trim((string) ($input['currency'] ?? 'USD')));
$redemptionInstructions = trim((string) ($input['redemption_instructions'] ?? '')) ?: null;
$expirationRule = trim((string) ($input['expiration_rule'] ?? 'none'));
$expirationDaysRaw = trim((string) ($input['expiration_days'] ?? ''));
$expirationDays = $expirationDaysRaw === '' ? null : max(1, (int) $expirationDaysRaw);
$expiresAt = trim((string) ($input['expires_at'] ?? '')) ?: null;
$quantityLimitRaw = trim((string) ($input['quantity_limit'] ?? ''));
$quantityLimit = $quantityLimitRaw === '' ? null : max(1, (int) $quantityLimitRaw);
$perUserLimit = max(1, (int) ($input['per_user_limit'] ?? 1));
$agentDiscoverable = !empty($input['agent_discoverable']) ? 1 : 0;
$agentSummary = trim((string) ($input['agent_summary'] ?? '')) ?: null;
$agentCategoriesJson = mg_reward_template_csv_json($input['agent_categories'] ?? '');
$agentUseCasesJson = mg_reward_template_csv_json($input['agent_use_cases'] ?? '');
$agentAddToWalletAllowed = (!empty($input['agent_add_to_wallet_allowed']) || $agentDiscoverable) ? 1 : 0;
$agentGiftSendAllowed = !empty($input['agent_gift_send_allowed']) ? 1 : 0;

if (
    ($templateId !== '' && (strlen($templateId) !== 36 || !preg_match('/^[a-f0-9-]{36}$/', $templateId)))
    || $title === '' || mb_strlen($title) > 180
    || !in_array($rewardType, ['dollar_credit','free_item','discount','perk_upgrade','event_reward','custom'], true)
    || !in_array($valueType, ['fixed_amount','percent','free_item','custom'], true)
    || !in_array($status, ['draft','active','paused','archived'], true)
    || !in_array($expirationRule, ['none','after_issue','after_claim','fixed_date','event_date'], true)
    || !preg_match('/^[A-Z]{3}$/', $currency)
    || ($valueType === 'percent' && $percentRaw !== '' && !preg_match('/^\d+(?:\.\d{1,2})?$/', $percentRaw))
    || ($valuePercent !== null && ($valuePercent <= 0 || $valuePercent > 100))
) {
    mg_fail('Invalid reward template.', 422);
}

try {
    if ($templateId === '') {
        $templateId = mg_merchant_uuid();
        $stmt = $pdo->prepare(
            'INSERT INTO reward_templates
             (public_id,merchant_user_id,title,description,reward_type,value_type,value_amount_cents,value_percent,currency,
              redemption_instructions,expiration_rule,expiration_days,expires_at,quantity_limit,per_user_limit,
              agent_discoverable,agent_summary,agent_categories_json,agent_use_cases_json,agent_add_to_wallet_allowed,agent_gift_send_allowed,status,created_at,updated_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())'
        );
        $stmt->execute([
            $templateId,$merchantId,$title,$description,$rewardType,$valueType,$valueAmountCents,$valuePercent,$currency,
            $redemptionInstructions,$expirationRule,$expirationDays,$expiresAt,$quantityLimit,$perUserLimit,
            $agentDiscoverable,$agentSummary,$agentCategoriesJson,$agentUseCasesJson,$agentAddToWalletAllowed,$agentGiftSendAllowed,$status,
        ]);
        $dbId = (int) $pdo->lastInsertId();
        $message = 'Reward template created.';
    } else {
        $lookup = $pdo->prepare('SELECT id FROM reward_templates WHERE public_id = ? AND merchant_user_id = ? LIMIT 1');
        $lookup->execute([$templateId, $merchantId]);
        $dbId = (int) ($lookup->fetchColumn() ?: 0);
        if ($dbId <= 0) mg_fail('Reward template not found.', 404);
        $stmt = $pdo->prepare(
            'UPDATE reward_templates
             SET title=?,description=?,reward_type=?,value_type=?,value_amount_cents=?,value_percent=?,currency=?,
                 redemption_instructions=?,expiration_rule=?,expiration_days=?,expires_at=?,quantity_limit=?,per_user_limit=?,
                 agent_discoverable=?,agent_summary=?,agent_categories_json=?,agent_use_cases_json=?,agent_add_to_wallet_allowed=?,agent_gift_send_allowed=?,status=?,updated_at=NOW()
             WHERE id=? AND public_id=? AND merchant_user_id=?'
        );
        $stmt->execute([
            $title,$description,$rewardType,$valueType,$valueAmountCents,$valuePercent,$currency,
            $redemptionInstructions,$expirationRule,$expirationDays,$expiresAt,$quantityLimit,$perUserLimit,
            $agentDiscoverable,$agentSummary,$agentCategoriesJson,$agentUseCasesJson,$agentAddToWalletAllowed,$agentGiftSendAllowed,$status,
            $dbId,$templateId,$merchantId,
        ]);
        $message = 'Reward template updated.';
    }

    $select = $pdo->prepare('SELECT * FROM reward_templates WHERE id = ? AND merchant_user_id = ? LIMIT 1');
    $select->execute([$dbId, $merchantId]);
    $row = $select->fetch();
    if (!$row) mg_fail('Reward template could not be loaded.', 500);

    mg_audit('merchant.reward_template_saved', 'reward_template', [
        'template_id' => $templateId,
        'reward_type' => $rewardType,
        'status' => $status,
        'agent_discoverable' => (bool) $agentDiscoverable,
    ], $merchantId);

    mg_ok(['template' => mg_reward_template_row($row), 'schema_ready' => true], $message, 201);
} catch (Throwable $error) {
    mg_security_log('error', 'merchant.reward_templates.save_failed', 'Unable to save reward template.', [
        'exception_class' => $error::class,
    ], $merchantId);
    mg_fail('Unable to save reward template.', 500);
}
