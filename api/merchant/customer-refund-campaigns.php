<?php

declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';

function mg_customer_refund_uuid(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 15) | 64);
    $bytes[8] = chr((ord($bytes[8]) & 63) | 128);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}

function mg_customer_refund_slug(string $title): string
{
    $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $title) ?? ''));
    $slug = trim($slug, '-');
    return substr($slug !== '' ? $slug : 'customer-refund', 0, 120);
}

function mg_customer_refund_unique_slug(PDO $pdo, int $merchantId, string $title, string $excludePublicId = ''): string
{
    $base = mg_customer_refund_slug($title);
    $candidate = $base;
    $suffix = 1;
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM campaigns WHERE merchant_user_id=? AND public_slug=? AND public_id<>?');
    while (true) {
        $stmt->execute([$merchantId, $candidate, $excludePublicId]);
        if ((int)$stmt->fetchColumn() === 0) return $candidate;
        $suffix++;
        $candidate = substr($base, 0, max(1, 120 - strlen((string)$suffix) - 1)) . '-' . $suffix;
    }
}

function mg_customer_refund_datetime(?string $value): ?string
{
    $raw = trim((string)$value);
    if ($raw === '') return null;
    $raw = str_replace('T', ' ', $raw);
    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $raw) === 1) $raw .= ':00';
    $ts = strtotime($raw);
    if ($ts === false) mg_fail('Invalid campaign date.', 422);
    return date('Y-m-d H:i:s', $ts);
}

function mg_customer_refund_reward_id(PDO $pdo, int $merchantId, string $publicId, string $campaignStatus): ?int
{
    $publicId = strtolower(trim($publicId));
    if ($publicId === '') return null;
    if (strlen($publicId) !== 36 || !preg_match('/^[a-f0-9-]{36}$/', $publicId)) mg_fail('Invalid reward template.', 422);
    $stmt = $pdo->prepare("SELECT id,status FROM reward_templates WHERE public_id=? AND merchant_user_id=? AND status<>'archived' LIMIT 1");
    $stmt->execute([$publicId, $merchantId]);
    $template = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$template) mg_fail('Reward template not found.', 404);
    if ($campaignStatus === 'active' && (string)$template['status'] !== 'active') mg_fail('Active Customer Refund campaigns require an active reward template.', 422);
    return (int)$template['id'];
}

function mg_customer_refund_decode_rules(mixed $json): array
{
    if (!is_string($json) || trim($json) === '') return [];
    $decoded = json_decode($json, true);
    return is_array($decoded) ? $decoded : [];
}

function mg_customer_refund_remaining(?int $limit, int $issued): ?int
{
    if ($limit === null) return null;
    return max(0, $limit - $issued);
}

function mg_customer_refund_row(array $row): array
{
    $campaignRemaining = mg_customer_refund_remaining($row['quantity_limit'] === null ? null : (int)$row['quantity_limit'], (int)($row['issued_count'] ?? 0));
    $rewardRemaining = mg_customer_refund_remaining($row['reward_template_quantity_limit'] === null ? null : (int)$row['reward_template_quantity_limit'], (int)($row['reward_template_issued_count'] ?? 0));
    $now = time();
    $isActive = (string)$row['status'] === 'active';
    $rewardReady = !empty($row['reward_template_public_id']) && (string)($row['reward_template_status'] ?? '') === 'active';
    $dateReady = (empty($row['starts_at']) || strtotime((string)$row['starts_at']) <= $now) && (empty($row['ends_at']) || strtotime((string)$row['ends_at']) >= $now);
    $campaignHasInventory = $campaignRemaining === null || $campaignRemaining > 0;
    $rewardHasInventory = $rewardRemaining === null || $rewardRemaining > 0;
    $eligible = $isActive && $rewardReady && $dateReady && $campaignHasInventory && $rewardHasInventory;
    $reason = 'Ready to send make-good vouchers.';
    if (!$isActive) $reason = 'Campaign must be active before it can send refunds.';
    elseif (!$rewardReady) $reason = 'Attach an active reward template before sending.';
    elseif (!$dateReady) $reason = 'Campaign is outside its active date window.';
    elseif (!$campaignHasInventory) $reason = 'Customer Refund campaign inventory is unavailable.';
    elseif (!$rewardHasInventory) $reason = 'Assigned reward inventory is unavailable.';

    return [
        'id' => (string)$row['public_id'],
        'title' => (string)$row['title'],
        'description' => (string)($row['description'] ?? ''),
        'campaign_type' => 'customer_refund',
        'status' => (string)$row['status'],
        'reward_template_id' => $row['reward_template_public_id'] ?? null,
        'reward_template_title' => $row['reward_template_title'] ?? null,
        'reward_template_status' => $row['reward_template_status'] ?? null,
        'quantity_limit' => $row['quantity_limit'] === null ? null : (int)$row['quantity_limit'],
        'issued_count' => (int)($row['issued_count'] ?? 0),
        'campaign_remaining' => $campaignRemaining,
        'reward_remaining' => $rewardRemaining,
        'starts_at' => $row['starts_at'] ?? null,
        'ends_at' => $row['ends_at'] ?? null,
        'eligible' => $eligible,
        'reason' => $reason,
        'rules' => mg_customer_refund_decode_rules($row['rules_json'] ?? null),
        'updated_at' => $row['updated_at'] ?? null,
    ];
}

function mg_customer_refund_select_sql(): string
{
    return 'SELECT c.*, rt.public_id reward_template_public_id, rt.title reward_template_title, rt.status reward_template_status,
        rt.quantity_limit reward_template_quantity_limit, rt.issued_count reward_template_issued_count
        FROM campaigns c
        LEFT JOIN reward_templates rt ON rt.id=c.reward_template_id
        WHERE c.merchant_user_id=? AND c.campaign_type=\'customer_refund\'';
}

function mg_customer_refund_active_usage(PDO $pdo, int $merchantId, string $excludePublicId = ''): int
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM campaigns WHERE merchant_user_id=? AND status='active' AND public_id<>?");
    $stmt->execute([$merchantId, $excludePublicId]);
    return (int)$stmt->fetchColumn();
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$user = $method === 'GET' ? mg_merchant_require_permission('merchant.campaigns.view') : mg_merchant_require_permission('merchant.campaigns.manage');
$merchantId = (int)$user['id'];
$pdo = mg_db();
mg_merchant_ensure_workspace($pdo, $user);

if ($method === 'GET') {
    try {
        $status = trim((string)($_GET['status'] ?? 'all'));
        $sql = mg_customer_refund_select_sql();
        $params = [$merchantId];
        if (in_array($status, ['draft', 'active', 'paused', 'ended', 'archived'], true)) {
            $sql .= ' AND c.status=?';
            $params[] = $status;
        } else {
            $sql .= " AND c.status<>'archived'";
        }
        $sql .= ' ORDER BY c.status=\'active\' DESC, c.updated_at DESC, c.id DESC LIMIT 100';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $campaigns = array_map('mg_customer_refund_row', $stmt->fetchAll(PDO::FETCH_ASSOC));
        mg_ok(['campaigns' => $campaigns, 'eligible_count' => count(array_filter($campaigns, fn($c) => !empty($c['eligible']))), 'schema_ready' => true]);
    } catch (Throwable $error) {
        mg_security_log('warning', 'merchant.customer_refund_campaigns.unavailable', 'Customer Refund campaigns are unavailable.', ['exception_class' => $error::class, 'message' => $error->getMessage()], $merchantId);
        mg_ok(['campaigns' => [], 'eligible_count' => 0, 'schema_ready' => false], 'Customer Refund campaigns unavailable until the campaign type migration is installed.');
    }
}

if ($method !== 'POST') mg_fail('Method not allowed.', 405);
$input = mg_input();
mg_require_csrf_for_write($input);

$campaignId = strtolower(trim((string)($input['campaign_id'] ?? '')));
$title = trim((string)($input['title'] ?? ''));
$status = trim((string)($input['status'] ?? 'draft'));
$description = trim((string)($input['description'] ?? '')) ?: null;
$formHeadline = trim((string)($input['form_headline'] ?? 'Customer Refund')) ?: null;
$formDescription = trim((string)($input['form_description'] ?? 'Internal make-good voucher campaign for Merchant CRM.')) ?: null;
$successMessage = trim((string)($input['success_message'] ?? 'Customer refund voucher issued.')) ?: null;
$startsAt = mg_customer_refund_datetime((string)($input['starts_at'] ?? ''));
$endsAt = mg_customer_refund_datetime((string)($input['ends_at'] ?? ''));
$quantityLimitRaw = trim((string)($input['quantity_limit'] ?? ''));
$quantityLimit = $quantityLimitRaw === '' ? null : max(1, (int)$quantityLimitRaw);
$perUserLimit = max(1, (int)($input['per_user_limit'] ?? 1));
$rewardTemplateId = mg_customer_refund_reward_id($pdo, $merchantId, (string)($input['reward_template_id'] ?? ''), $status);
$rulesJson = json_encode([
    'campaign_type' => 'customer_refund',
    'version' => 1,
    'mode' => 'merchant_make_good',
    'internal_only' => true,
    'entry_reward_enabled' => false,
    'instructions' => trim((string)($input['customer_refund_instructions'] ?? '')) ?: 'Issue make-good vouchers from Merchant CRM contacts.',
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

if (($campaignId !== '' && (strlen($campaignId) !== 36 || !preg_match('/^[a-f0-9-]{36}$/', $campaignId))) || $title === '' || mb_strlen($title) > 180 || !in_array($status, ['draft', 'active', 'paused', 'ended', 'archived'], true)) {
    mg_fail('Invalid Customer Refund campaign.', 422);
}
if ($startsAt !== null && $endsAt !== null && strtotime($startsAt) >= strtotime($endsAt)) mg_fail('Campaign end date must be after the start date.', 422);
if ($status === 'active' && $rewardTemplateId === null) mg_fail('Active Customer Refund campaigns require an attached reward template.', 422);
if ($status === 'active') mg_package_require_limit_available($pdo, $user, 'max_active_campaigns', mg_customer_refund_active_usage($pdo, $merchantId, $campaignId), 'Active campaign limit reached.');

try {
    $isNew = $campaignId === '';
    if ($isNew) {
        $campaignId = mg_customer_refund_uuid();
        $slug = mg_customer_refund_unique_slug($pdo, $merchantId, $title);
        $stmt = $pdo->prepare('INSERT INTO campaigns (public_id,merchant_user_id,reward_template_id,campaign_type,title,description,form_headline,form_description,success_message,status,starts_at,ends_at,quantity_limit,per_user_limit,agent_discoverable,public_slug,qr_code_token,rules_json,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())');
        $stmt->execute([$campaignId,$merchantId,$rewardTemplateId,'customer_refund',$title,$description,$formHeadline,$formDescription,$successMessage,$status,$startsAt,$endsAt,$quantityLimit,$perUserLimit,0,$slug,null,$rulesJson]);
        $dbId = (int)$pdo->lastInsertId();
        $message = 'Customer Refund campaign created.';
    } else {
        $lookup = $pdo->prepare("SELECT id FROM campaigns WHERE public_id=? AND merchant_user_id=? AND campaign_type='customer_refund' LIMIT 1");
        $lookup->execute([$campaignId, $merchantId]);
        $dbId = (int)($lookup->fetchColumn() ?: 0);
        if ($dbId <= 0) mg_fail('Customer Refund campaign not found.', 404);
        $slug = mg_customer_refund_unique_slug($pdo, $merchantId, $title, $campaignId);
        $stmt = $pdo->prepare('UPDATE campaigns SET reward_template_id=?,title=?,description=?,form_headline=?,form_description=?,success_message=?,status=?,starts_at=?,ends_at=?,quantity_limit=?,per_user_limit=?,agent_discoverable=0,public_slug=?,qr_code_token=NULL,rules_json=?,updated_at=NOW() WHERE id=? AND public_id=? AND merchant_user_id=?');
        $stmt->execute([$rewardTemplateId,$title,$description,$formHeadline,$formDescription,$successMessage,$status,$startsAt,$endsAt,$quantityLimit,$perUserLimit,$slug,$rulesJson,$dbId,$campaignId,$merchantId]);
        $message = 'Customer Refund campaign updated.';
    }

    $select = $pdo->prepare(mg_customer_refund_select_sql() . ' AND c.id=? LIMIT 1');
    $select->execute([$merchantId, $dbId]);
    $row = $select->fetch(PDO::FETCH_ASSOC);
    if (!$row) mg_fail('Customer Refund campaign could not be loaded.', 500);
    mg_audit('merchant.customer_refund_campaign_saved', 'campaign', ['campaign_id' => $campaignId, 'status' => $status, 'reward_attached' => $rewardTemplateId !== null], $merchantId);
    mg_ok(['campaign' => mg_customer_refund_row($row), 'schema_ready' => true], $message, 201);
} catch (Throwable $error) {
    mg_security_log('error', 'merchant.customer_refund_campaigns.save_failed', 'Unable to save Customer Refund campaign.', ['exception_class' => $error::class, 'message' => $error->getMessage()], $merchantId);
    mg_fail('Unable to save Customer Refund campaign.', 500);
}
