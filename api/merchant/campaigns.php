<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';
require_once dirname(__DIR__) . '/public/campaigns/_merchant_notifications.php';

function mg_campaign_slug(string $title): string
{
    $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $title) ?? ''));
    $slug = trim($slug, '-');
    return substr($slug !== '' ? $slug : 'campaign', 0, 120);
}

function mg_campaign_unique_slug(PDO $pdo, int $merchantId, string $title, string $excludePublicId = ''): string
{
    $base = mg_campaign_slug($title);
    $candidate = $base;
    $suffix = 1;
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM campaigns WHERE merchant_user_id = ? AND public_slug = ? AND public_id <> ?');
    while (true) {
        $stmt->execute([$merchantId, $candidate, $excludePublicId]);
        if ((int) $stmt->fetchColumn() === 0) return $candidate;
        $suffix++;
        $candidate = substr($base, 0, max(1, 120 - strlen((string) $suffix) - 1)) . '-' . $suffix;
    }
}

function mg_campaign_decode_rules(mixed $json): array
{
    if (!is_string($json) || trim($json) === '') return [];
    $decoded = json_decode($json, true);
    return is_array($decoded) ? $decoded : [];
}

function mg_campaign_datetime(?string $value): ?string
{
    $raw = trim((string) $value);
    if ($raw === '') return null;
    $raw = str_replace('T', ' ', $raw);
    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $raw) === 1) $raw .= ':00';
    $ts = strtotime($raw);
    if ($ts === false) mg_fail('Invalid campaign date.', 422);
    return date('Y-m-d H:i:s', $ts);
}

function mg_campaign_build_rules(string $campaignType, array $input, ?int $quantityLimit): ?string
{
    $rules = ['campaign_type' => $campaignType, 'version' => 1];
    if ($campaignType === 'contest_giveaway') {
        $mode = trim((string)($input['contest_mode'] ?? 'first_x'));
        $allowed = ['first_x', 'instant_reward', 'random_draw', 'manual_winner'];
        if (!in_array($mode, $allowed, true)) $mode = 'first_x';
        $winnerLimitRaw = trim((string)($input['contest_winner_limit'] ?? ''));
        $winnerLimit = $winnerLimitRaw === '' ? null : max(1, (int)$winnerLimitRaw);
        if ($mode === 'first_x' && $winnerLimit === null) $winnerLimit = $quantityLimit ?? 100;
        $drawAt = mg_campaign_datetime((string)($input['contest_draw_at'] ?? ''));
        $rules += [
            'mode' => $mode,
            'winner_limit' => $winnerLimit,
            'draw_at' => $drawAt,
            'entry_reward_enabled' => $mode === 'first_x' || $mode === 'instant_reward' || !empty($input['contest_entry_reward_enabled']),
            'official_rules' => trim((string)($input['contest_rules'] ?? '')) ?: null,
        ];
    } elseif ($campaignType === 'qr_reward_drop') {
        $rules += ['mode' => 'qr_claim', 'entry_reward_enabled' => true];
    } elseif ($campaignType === 'referral_reward') {
        $rules += ['mode' => 'referral_capture', 'instructions' => trim((string)($input['referral_instructions'] ?? '')) ?: null];
    } elseif ($campaignType === 'birthday_vip') {
        $rules += ['mode' => 'birthday_capture', 'instructions' => trim((string)($input['vip_instructions'] ?? '')) ?: null];
    } elseif ($campaignType === 'agent_offer') {
        $rules += ['mode' => 'agent_interest', 'instructions' => trim((string)($input['agent_offer_instructions'] ?? '')) ?: null];
    } else {
        $rules += ['mode' => 'instant_reward', 'entry_reward_enabled' => true];
    }
    return json_encode($rules, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

function mg_campaign_row(array $row): array
{
    $rules = mg_campaign_decode_rules($row['rules_json'] ?? null);
    return [
        'id' => (string) $row['public_id'],
        'reward_template_id' => $row['reward_template_public_id'] ?? null,
        'reward_template_title' => $row['reward_template_title'] ?? null,
        'reward_template_status' => $row['reward_template_status'] ?? null,
        'reward_attached' => !empty($row['reward_template_public_id']),
        'campaign_type' => (string) $row['campaign_type'],
        'title' => (string) $row['title'],
        'description' => (string) ($row['description'] ?? ''),
        'form_headline' => (string) ($row['form_headline'] ?? ''),
        'form_description' => (string) ($row['form_description'] ?? ''),
        'success_message' => (string) ($row['success_message'] ?? ''),
        'status' => (string) $row['status'],
        'starts_at' => $row['starts_at'] ?? null,
        'ends_at' => $row['ends_at'] ?? null,
        'quantity_limit' => $row['quantity_limit'] === null ? null : (int) $row['quantity_limit'],
        'issued_count' => (int) ($row['issued_count'] ?? 0),
        'per_user_limit' => (int) ($row['per_user_limit'] ?? 1),
        'agent_discoverable' => (bool) ((int) ($row['agent_discoverable'] ?? 0)),
        'public_slug' => $row['public_slug'] ?? null,
        'qr_code_token' => $row['qr_code_token'] ?? null,
        'rules' => $rules,
        'activity' => [
            'contacts' => (int) ($row['contact_count'] ?? 0),
            'wallet_items' => (int) ($row['wallet_item_count'] ?? 0),
            'events' => (int) ($row['event_count'] ?? 0),
            'last_event_at' => $row['last_event_at'] ?? null,
        ],
        'created_at' => $row['created_at'] ?? null,
        'updated_at' => $row['updated_at'] ?? null,
    ];
}

function mg_campaign_reward_template_id(PDO $pdo, int $merchantId, string $publicId, string $campaignStatus): ?int
{
    $publicId = strtolower(trim($publicId));
    if ($publicId === '') return null;
    if (strlen($publicId) !== 36 || !preg_match('/^[a-f0-9-]{36}$/', $publicId)) mg_fail('Invalid reward template.', 422);
    $stmt = $pdo->prepare('SELECT id,status FROM reward_templates WHERE public_id = ? AND merchant_user_id = ? AND status <> \'archived\' LIMIT 1');
    $stmt->execute([$publicId, $merchantId]);
    $template = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$template) mg_fail('Reward template not found.', 404);
    if ($campaignStatus === 'active' && (string)$template['status'] !== 'active') {
        mg_fail('Active campaigns require an active reward template.', 422);
    }
    return (int)$template['id'];
}

function mg_campaign_requires_reward_template(string $campaignType, string $status): bool
{
    return $status === 'active' && in_array($campaignType, ['newsletter_signup', 'contest_giveaway', 'qr_reward_drop', 'referral_reward', 'birthday_vip', 'agent_offer'], true);
}

function mg_campaign_active_usage(PDO $pdo, int $merchantId, string $excludePublicId = ''): int
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM campaigns WHERE merchant_user_id = ? AND status = \'active\' AND public_id <> ?');
    $stmt->execute([$merchantId, $excludePublicId]);
    return (int) $stmt->fetchColumn();
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$user = $method === 'GET' ? mg_merchant_require_permission('merchant.campaigns.view') : mg_merchant_require_permission('merchant.campaigns.manage');
$merchantId = (int) $user['id'];
$pdo = mg_db();
mg_merchant_ensure_workspace($pdo, $user);

if ($method === 'GET') {
    try {
        $status = trim((string) ($_GET['status'] ?? 'all'));
        $allowedStatus = ['draft', 'active', 'paused', 'ended', 'archived'];
        $sql = 'SELECT c.*, rt.public_id reward_template_public_id, rt.title reward_template_title, rt.status reward_template_status,
                    (SELECT COUNT(*) FROM campaign_contacts cc WHERE cc.campaign_id = c.id) contact_count,
                    (SELECT COUNT(*) FROM wallet_items wi WHERE wi.campaign_id = c.id AND wi.status <> \'cancelled\') wallet_item_count,
                    (SELECT COUNT(*) FROM campaign_events ce WHERE ce.campaign_id = c.id) event_count,
                    (SELECT MAX(ce2.created_at) FROM campaign_events ce2 WHERE ce2.campaign_id = c.id) last_event_at
                FROM campaigns c
                LEFT JOIN reward_templates rt ON rt.id = c.reward_template_id
                WHERE c.merchant_user_id = ?';
        $params = [$merchantId];
        if (in_array($status, $allowedStatus, true)) {
            $sql .= ' AND c.status = ?';
            $params[] = $status;
        }
        $sql .= ' ORDER BY c.updated_at DESC, c.id DESC LIMIT 100';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $campaigns = array_map('mg_campaign_row', $stmt->fetchAll());
        mg_ok(['campaigns' => $campaigns, 'schema_ready' => true, 'package' => mg_merchant_package_context($pdo, $user)]);
    } catch (Throwable $error) {
        mg_security_log('warning', 'merchant.campaigns.schema_unavailable', 'Campaign schema is unavailable.', ['exception_class' => $error::class], $merchantId);
        mg_ok(['campaigns' => [], 'schema_ready' => false], 'Campaigns unavailable until the Stage 12 schema is installed.');
    }
}

if ($method !== 'POST') mg_fail('Method not allowed.', 405);
$input = mg_input();
mg_require_csrf_for_write($input);

$campaignId = strtolower(trim((string) ($input['campaign_id'] ?? '')));
$title = trim((string) ($input['title'] ?? ''));
$campaignType = trim((string) ($input['campaign_type'] ?? 'newsletter_signup'));
$status = trim((string) ($input['status'] ?? 'draft'));
$description = trim((string) ($input['description'] ?? '')) ?: null;
$formHeadline = trim((string) ($input['form_headline'] ?? '')) ?: null;
$formDescription = trim((string) ($input['form_description'] ?? '')) ?: null;
$successMessage = trim((string) ($input['success_message'] ?? '')) ?: null;
$startsAt = mg_campaign_datetime((string) ($input['starts_at'] ?? ''));
$endsAt = mg_campaign_datetime((string) ($input['ends_at'] ?? ''));
$quantityLimitRaw = trim((string) ($input['quantity_limit'] ?? ''));
$quantityLimit = $quantityLimitRaw === '' ? null : max(1, (int) $quantityLimitRaw);
$perUserLimit = max(1, (int) ($input['per_user_limit'] ?? 1));
$agentDiscoverable = !empty($input['agent_discoverable']) ? 1 : 0;

if (
    ($campaignId !== '' && (strlen($campaignId) !== 36 || !preg_match('/^[a-f0-9-]{36}$/', $campaignId)))
    || $title === '' || mb_strlen($title) > 180
    || !in_array($campaignType, ['newsletter_signup', 'contest_giveaway', 'qr_reward_drop', 'referral_reward', 'birthday_vip', 'agent_offer'], true)
    || !in_array($status, ['draft', 'active', 'paused', 'ended', 'archived'], true)
) {
    mg_fail('Invalid campaign.', 422);
}
if ($startsAt !== null && $endsAt !== null && strtotime($startsAt) >= strtotime($endsAt)) {
    mg_fail('Campaign end date must be after the start date.', 422);
}
if ($campaignType === 'contest_giveaway' && trim((string)($input['contest_mode'] ?? 'first_x')) === 'first_x' && $quantityLimit === null) {
    $quantityLimit = max(1, (int)($input['contest_winner_limit'] ?? 100));
}
$rewardTemplateId = mg_campaign_reward_template_id($pdo, $merchantId, (string) ($input['reward_template_id'] ?? ''), $status);
$rulesJson = mg_campaign_build_rules($campaignType, $input, $quantityLimit);

if (mg_campaign_requires_reward_template($campaignType, $status) && $rewardTemplateId === null) {
    mg_fail('Active campaigns require an attached reward template.', 422);
}
if ($status === 'active') {
    mg_package_require_limit_available($pdo, $user, 'max_active_campaigns', mg_campaign_active_usage($pdo, $merchantId, $campaignId), 'Active campaign limit reached.');
}

try {
    $previousStatus = null;
    $isNew = $campaignId === '';
    if ($isNew) {
        $campaignId = mg_merchant_uuid();
        $slug = mg_campaign_unique_slug($pdo, $merchantId, $title);
        $qrToken = $campaignType === 'qr_reward_drop' ? bin2hex(random_bytes(16)) : null;
        $stmt = $pdo->prepare('INSERT INTO campaigns
            (public_id,merchant_user_id,reward_template_id,campaign_type,title,description,form_headline,form_description,success_message,status,starts_at,ends_at,quantity_limit,per_user_limit,agent_discoverable,public_slug,qr_code_token,rules_json,created_at,updated_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())');
        $stmt->execute([$campaignId,$merchantId,$rewardTemplateId,$campaignType,$title,$description,$formHeadline,$formDescription,$successMessage,$status,$startsAt,$endsAt,$quantityLimit,$perUserLimit,$agentDiscoverable,$slug,$qrToken,$rulesJson]);
        $dbId = (int) $pdo->lastInsertId();
        $message = 'Campaign created.';
    } else {
        $lookup = $pdo->prepare('SELECT id, qr_code_token, status FROM campaigns WHERE public_id = ? AND merchant_user_id = ? LIMIT 1');
        $lookup->execute([$campaignId, $merchantId]);
        $existing = $lookup->fetch(PDO::FETCH_ASSOC);
        $dbId = (int) ($existing['id'] ?? 0);
        if ($dbId <= 0) mg_fail('Campaign not found.', 404);
        $previousStatus = (string)($existing['status'] ?? '');
        $slug = mg_campaign_unique_slug($pdo, $merchantId, $title, $campaignId);
        $qrToken = $campaignType === 'qr_reward_drop' ? ((string)($existing['qr_code_token'] ?? '') ?: bin2hex(random_bytes(16))) : null;
        $stmt = $pdo->prepare('UPDATE campaigns
            SET reward_template_id=?,campaign_type=?,title=?,description=?,form_headline=?,form_description=?,success_message=?,status=?,starts_at=?,ends_at=?,quantity_limit=?,per_user_limit=?,agent_discoverable=?,public_slug=?,qr_code_token=?,rules_json=?,updated_at=NOW()
            WHERE id=? AND public_id=? AND merchant_user_id=?');
        $stmt->execute([$rewardTemplateId,$campaignType,$title,$description,$formHeadline,$formDescription,$successMessage,$status,$startsAt,$endsAt,$quantityLimit,$perUserLimit,$agentDiscoverable,$slug,$qrToken,$rulesJson,$dbId,$campaignId,$merchantId]);
        $message = 'Campaign updated.';
    }

    $select = $pdo->prepare('SELECT c.*, rt.public_id reward_template_public_id, rt.title reward_template_title, rt.status reward_template_status,
            0 contact_count, 0 wallet_item_count, 0 event_count, NULL last_event_at
        FROM campaigns c LEFT JOIN reward_templates rt ON rt.id = c.reward_template_id
        WHERE c.id = ? AND c.merchant_user_id = ? LIMIT 1');
    $select->execute([$dbId, $merchantId]);
    $row = $select->fetch(PDO::FETCH_ASSOC);
    if (!$row) mg_fail('Campaign could not be loaded.', 500);

    $notification = ['created' => false, 'reason' => 'not_required'];
    if ($status === 'active' && ($isNew || $previousStatus !== 'active')) {
        $notification = mg_public_campaign_notify_merchant_lifecycle($pdo, $row, 'campaign.launched');
    } elseif ($isNew) {
        $notification = mg_public_campaign_notify_merchant_lifecycle($pdo, $row, 'campaign.created');
    }

    mg_audit('merchant.campaign_saved', 'campaign', [
        'campaign_id' => $campaignId,
        'campaign_type' => $campaignType,
        'status' => $status,
        'reward_attached' => $rewardTemplateId !== null,
        'rules' => mg_campaign_decode_rules($rulesJson),
        'notification' => $notification,
    ], $merchantId);

    mg_ok(['campaign' => mg_campaign_row($row), 'notification' => $notification, 'schema_ready' => true, 'package' => mg_merchant_package_context($pdo, $user)], $message, 201);
} catch (Throwable $error) {
    mg_security_log('error', 'merchant.campaigns.save_failed', 'Unable to save campaign.', ['exception_class' => $error::class], $merchantId);
    mg_fail('Unable to save campaign.', 500);
}
