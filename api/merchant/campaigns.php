<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';

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

function mg_campaign_public_path(string $campaignType): string
{
    return match ($campaignType) {
        'newsletter_signup' => '/newsletter-signup.php',
        'contest_giveaway' => '/contest-entry.php',
        'qr_reward_drop' => '/qr-drop.php',
        'referral_reward' => '/referral-reward.php',
        'birthday_vip' => '/birthday-vip.php',
        'agent_offer' => '/agent-offer.php',
        default => '/campaign.php',
    };
}

function mg_campaign_public_url(array $row): string
{
    $type = (string) $row['campaign_type'];
    $ref = (string) ($row['public_slug'] ?: $row['public_id']);
    $path = mg_campaign_public_path($type);
    $query = $type === 'qr_reward_drop' && !empty($row['qr_code_token'])
        ? ('?token=' . rawurlencode((string) $row['qr_code_token']))
        : ('?campaign=' . rawurlencode($ref));
    return $path . $query;
}

function mg_campaign_row(array $row): array
{
    return [
        'id' => (string) $row['public_id'],
        'reward_template_id' => $row['reward_template_public_id'] ?? null,
        'reward_template_title' => $row['reward_template_title'] ?? null,
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
        'public_url' => mg_campaign_public_url($row),
        'landing_page_url' => mg_campaign_public_url($row),
        'created_at' => $row['created_at'] ?? null,
        'updated_at' => $row['updated_at'] ?? null,
    ];
}

function mg_campaign_reward_template_id(PDO $pdo, int $merchantId, string $publicId): ?int
{
    $publicId = strtolower(trim($publicId));
    if ($publicId === '') return null;
    if (strlen($publicId) !== 36 || !preg_match('/^[a-f0-9-]{36}$/', $publicId)) mg_fail('Invalid reward template.', 422);
    $stmt = $pdo->prepare('SELECT id FROM reward_templates WHERE public_id = ? AND merchant_user_id = ? AND status <> \'archived\' LIMIT 1');
    $stmt->execute([$publicId, $merchantId]);
    $id = (int) ($stmt->fetchColumn() ?: 0);
    if ($id <= 0) mg_fail('Reward template not found.', 404);
    return $id;
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$user = $method === 'GET' ? mg_require_permission('merchant.campaigns.view') : mg_require_permission('merchant.campaigns.manage');
$merchantId = (int) $user['id'];
$pdo = mg_db();
mg_merchant_ensure_workspace($pdo, $user);

if ($method === 'GET') {
    try {
        $status = trim((string) ($_GET['status'] ?? 'all'));
        $allowedStatus = ['draft', 'active', 'paused', 'ended', 'archived'];
        $sql = 'SELECT c.*, rt.public_id reward_template_public_id, rt.title reward_template_title
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
        mg_ok(['campaigns' => $campaigns, 'schema_ready' => true]);
    } catch (Throwable $error) {
        mg_security_log('warning', 'merchant.campaigns.schema_unavailable', 'Campaign schema is unavailable.', [
            'exception_class' => $error::class,
        ], $merchantId);
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
$rewardTemplateId = mg_campaign_reward_template_id($pdo, $merchantId, (string) ($input['reward_template_id'] ?? ''));
$description = trim((string) ($input['description'] ?? '')) ?: null;
$formHeadline = trim((string) ($input['form_headline'] ?? '')) ?: null;
$formDescription = trim((string) ($input['form_description'] ?? '')) ?: null;
$successMessage = trim((string) ($input['success_message'] ?? '')) ?: null;
$startsAt = trim((string) ($input['starts_at'] ?? '')) ?: null;
$endsAt = trim((string) ($input['ends_at'] ?? '')) ?: null;
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

try {
    if ($campaignId === '') {
        $campaignId = mg_merchant_uuid();
        $slug = mg_campaign_unique_slug($pdo, $merchantId, $title);
        $qrToken = $campaignType === 'qr_reward_drop' ? bin2hex(random_bytes(16)) : null;
        $stmt = $pdo->prepare('INSERT INTO campaigns
            (public_id,merchant_user_id,reward_template_id,campaign_type,title,description,form_headline,form_description,success_message,status,starts_at,ends_at,quantity_limit,per_user_limit,agent_discoverable,public_slug,qr_code_token,created_at,updated_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())');
        $stmt->execute([$campaignId,$merchantId,$rewardTemplateId,$campaignType,$title,$description,$formHeadline,$formDescription,$successMessage,$status,$startsAt,$endsAt,$quantityLimit,$perUserLimit,$agentDiscoverable,$slug,$qrToken]);
        $dbId = (int) $pdo->lastInsertId();
        $message = 'Campaign created.';
    } else {
        $lookup = $pdo->prepare('SELECT id FROM campaigns WHERE public_id = ? AND merchant_user_id = ? LIMIT 1');
        $lookup->execute([$campaignId, $merchantId]);
        $dbId = (int) ($lookup->fetchColumn() ?: 0);
        if ($dbId <= 0) mg_fail('Campaign not found.', 404);
        $slug = mg_campaign_unique_slug($pdo, $merchantId, $title, $campaignId);
        $stmt = $pdo->prepare('UPDATE campaigns
            SET reward_template_id=?,campaign_type=?,title=?,description=?,form_headline=?,form_description=?,success_message=?,status=?,starts_at=?,ends_at=?,quantity_limit=?,per_user_limit=?,agent_discoverable=?,public_slug=?,updated_at=NOW()
            WHERE id=? AND public_id=? AND merchant_user_id=?');
        $stmt->execute([$rewardTemplateId,$campaignType,$title,$description,$formHeadline,$formDescription,$successMessage,$status,$startsAt,$endsAt,$quantityLimit,$perUserLimit,$agentDiscoverable,$slug,$dbId,$campaignId,$merchantId]);
        $message = 'Campaign updated.';
    }

    $select = $pdo->prepare('SELECT c.*, rt.public_id reward_template_public_id, rt.title reward_template_title
        FROM campaigns c LEFT JOIN reward_templates rt ON rt.id = c.reward_template_id
        WHERE c.id = ? AND c.merchant_user_id = ? LIMIT 1');
    $select->execute([$dbId, $merchantId]);
    $row = $select->fetch();
    if (!$row) mg_fail('Campaign could not be loaded.', 500);

    mg_audit('merchant.campaign_saved', 'campaign', [
        'campaign_id' => $campaignId,
        'campaign_type' => $campaignType,
        'status' => $status,
        'public_url' => mg_campaign_public_url($row),
    ], $merchantId);

    mg_ok(['campaign' => mg_campaign_row($row), 'schema_ready' => true], $message, 201);
} catch (Throwable $error) {
    mg_security_log('error', 'merchant.campaigns.save_failed', 'Unable to save campaign.', [
        'exception_class' => $error::class,
    ], $merchantId);
    mg_fail('Unable to save campaign.', 500);
}
