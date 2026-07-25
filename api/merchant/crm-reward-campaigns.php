<?php

declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';

function mg_crm_reward_campaign_label(string $type): string
{
    return match ($type) {
        'customer_refund' => 'Customer Refund / Make Good',
        'referral_reward' => 'Referral Reward',
        'newsletter_signup' => 'Newsletter Signup',
        'contest_giveaway' => 'Contest / Giveaway',
        default => ucwords(str_replace('_', ' ', $type)),
    };
}

function mg_crm_reward_campaign_remaining(?int $limit, int $issued): ?int
{
    if ($limit === null) return null;
    return max(0, $limit - $issued);
}

function mg_crm_reward_campaign_row(array $row): array
{
    $campaignType = (string)($row['campaign_type'] ?? '');
    $campaignRemaining = mg_crm_reward_campaign_remaining($row['quantity_limit'] === null ? null : (int)$row['quantity_limit'], (int)($row['issued_count'] ?? 0));
    $rewardRemaining = mg_crm_reward_campaign_remaining($row['reward_template_quantity_limit'] === null ? null : (int)$row['reward_template_quantity_limit'], (int)($row['reward_template_issued_count'] ?? 0));
    $now = time();
    $isActive = (string)($row['status'] ?? '') === 'active';
    $rewardReady = !empty($row['reward_template_public_id']) && (string)($row['reward_template_status'] ?? '') === 'active';
    $dateReady = (empty($row['starts_at']) || strtotime((string)$row['starts_at']) <= $now) && (empty($row['ends_at']) || strtotime((string)$row['ends_at']) >= $now);
    $campaignHasInventory = $campaignRemaining === null || $campaignRemaining > 0;
    $rewardHasInventory = $rewardRemaining === null || $rewardRemaining > 0;
    $eligible = $isActive && $rewardReady && $dateReady && $campaignHasInventory && $rewardHasInventory;

    $typeLabel = mg_crm_reward_campaign_label($campaignType);
    $reason = $typeLabel . ' campaign is ready to send.';
    if (!$isActive) $reason = $typeLabel . ' campaign must be active before sending.';
    elseif (!$rewardReady) $reason = 'Attach an active reward template before sending.';
    elseif (!$dateReady) $reason = $typeLabel . ' campaign is outside its active date window.';
    elseif (!$campaignHasInventory) $reason = $typeLabel . ' campaign inventory is unavailable.';
    elseif (!$rewardHasInventory) $reason = 'Assigned reward inventory is unavailable.';

    return [
        'id' => (string)$row['public_id'],
        'title' => (string)$row['title'],
        'description' => (string)($row['description'] ?? ''),
        'campaign_type' => $campaignType,
        'campaign_type_label' => $typeLabel,
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
        'updated_at' => $row['updated_at'] ?? null,
    ];
}

mg_require_method('GET');
$user = mg_merchant_require_permission('merchant.campaigns.view');
$merchantId = (int)$user['id'];
$pdo = mg_db();
mg_merchant_ensure_workspace($pdo, $user);

$type = strtolower(trim((string)($_GET['type'] ?? $_GET['campaign_type'] ?? '')));
$allowedTypes = ['customer_refund', 'referral_reward', 'newsletter_signup', 'contest_giveaway', 'qr_reward_drop', 'birthday_vip', 'agent_offer'];
if ($type !== '' && !in_array($type, $allowedTypes, true)) mg_fail('Unsupported reward campaign type.', 422);

try {
    $where = "c.merchant_user_id=? AND c.status='active'";
    $params = [$merchantId];
    if ($type !== '') {
        $where .= ' AND c.campaign_type=?';
        $params[] = $type;
    }

    $sql = "SELECT c.*, rt.public_id reward_template_public_id, rt.title reward_template_title, rt.status reward_template_status,
            rt.quantity_limit reward_template_quantity_limit, rt.issued_count reward_template_issued_count
        FROM campaigns c
        LEFT JOIN reward_templates rt ON rt.id=c.reward_template_id
        WHERE {$where}
        ORDER BY c.updated_at DESC, c.id DESC
        LIMIT 100";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $campaigns = array_map('mg_crm_reward_campaign_row', $stmt->fetchAll(PDO::FETCH_ASSOC));
    mg_ok([
        'campaigns' => $campaigns,
        'eligible_count' => count(array_filter($campaigns, fn($campaign) => !empty($campaign['eligible']))),
        'allowed_types' => $type === '' ? ['all_active_merchant_campaigns'] : [$type],
        'filter_type' => $type,
        'schema_ready' => true,
    ]);
} catch (Throwable $error) {
    mg_security_log('warning', 'merchant.crm_reward_campaigns.unavailable', 'CRM reward campaigns are unavailable.', ['exception_class' => $error::class, 'message' => $error->getMessage()], $merchantId);
    mg_ok(['campaigns' => [], 'eligible_count' => 0, 'schema_ready' => false, 'filter_type' => $type], 'Reward campaigns unavailable until the campaign schema is installed.');
}
