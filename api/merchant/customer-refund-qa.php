<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';
require_once dirname(__DIR__, 2) . '/includes/campaign-types.php';

function mg_crqa_money(int $cents, string $currency = 'USD'): string
{
    return strtoupper($currency ?: 'USD') . ' ' . number_format($cents / 100, 2);
}

function mg_crqa_remaining(?int $limit, int $issued): ?int
{
    if ($limit === null) return null;
    return max(0, $limit - $issued);
}

function mg_crqa_check(string $key, string $label, bool $pass, string $detail = ''): array
{
    return ['key' => $key, 'label' => $label, 'pass' => $pass, 'detail' => $detail];
}

function mg_crqa_campaign_row(array $row): array
{
    $campaignLimit = $row['quantity_limit'] === null ? null : (int)$row['quantity_limit'];
    $rewardLimit = $row['reward_quantity_limit'] === null ? null : (int)$row['reward_quantity_limit'];
    $campaignIssued = (int)($row['issued_count'] ?? 0);
    $rewardIssued = (int)($row['reward_issued_count'] ?? 0);
    $campaignRemaining = mg_crqa_remaining($campaignLimit, $campaignIssued);
    $rewardRemaining = mg_crqa_remaining($rewardLimit, $rewardIssued);
    $now = time();
    $startsOk = empty($row['starts_at']) || strtotime((string)$row['starts_at']) <= $now;
    $endsOk = empty($row['ends_at']) || strtotime((string)$row['ends_at']) >= $now;
    $checks = [];
    $checks[] = mg_crqa_check('internal_only', 'Customer Refund is internal-only', !mg_campaign_type_public_enabled('customer_refund'), 'No public landing page should be generated.');
    $checks[] = mg_crqa_check('campaign_active', 'Campaign is active', (string)$row['status'] === 'active', (string)$row['status']);
    $checks[] = mg_crqa_check('reward_attached', 'Reward template attached', trim((string)($row['reward_template_public_id'] ?? '')) !== '', (string)($row['reward_template_title'] ?? 'No reward attached'));
    $checks[] = mg_crqa_check('reward_active', 'Reward template is active', (string)($row['reward_status'] ?? '') === 'active', (string)($row['reward_status'] ?? 'missing'));
    $checks[] = mg_crqa_check('date_window', 'Campaign is inside date window', $startsOk && $endsOk, trim((string)($row['starts_at'] ?? '') . ' → ' . (string)($row['ends_at'] ?? ''), ' →'));
    $checks[] = mg_crqa_check('campaign_inventory', 'Campaign inventory available', $campaignRemaining === null || $campaignRemaining > 0, $campaignRemaining === null ? 'Unlimited' : (string)$campaignRemaining . ' remaining');
    $checks[] = mg_crqa_check('reward_inventory', 'Reward inventory available', $rewardRemaining === null || $rewardRemaining > 0, $rewardRemaining === null ? 'Unlimited' : (string)$rewardRemaining . ' remaining');
    $sendReady = true;
    $failureMessages = [];
    foreach ($checks as $check) {
        if (empty($check['pass'])) {
            $sendReady = false;
            $failureMessages[] = $check['label'] . ($check['detail'] !== '' ? ': ' . $check['detail'] : '');
        }
    }
    return [
        'id' => (string)$row['public_id'],
        'title' => (string)$row['title'],
        'campaign_type' => 'customer_refund',
        'campaign_type_label' => mg_campaign_type_label('customer_refund'),
        'status' => (string)$row['status'],
        'reward_template_id' => $row['reward_template_public_id'] ?? null,
        'reward_template_title' => $row['reward_template_title'] ?? null,
        'reward_template_status' => $row['reward_status'] ?? null,
        'reward_value' => mg_crqa_money((int)($row['value_amount_cents'] ?? 0), (string)($row['currency'] ?? 'USD')),
        'campaign_limit' => $campaignLimit,
        'campaign_issued' => $campaignIssued,
        'campaign_remaining' => $campaignRemaining,
        'reward_limit' => $rewardLimit,
        'reward_issued' => $rewardIssued,
        'reward_remaining' => $rewardRemaining,
        'wallet_issued' => (int)($row['wallet_issued'] ?? 0),
        'wallet_claimed' => (int)($row['wallet_claimed'] ?? 0),
        'wallet_redeemed' => (int)($row['wallet_redeemed'] ?? 0),
        'starts_at' => $row['starts_at'] ?? null,
        'ends_at' => $row['ends_at'] ?? null,
        'send_ready' => $sendReady,
        'failure_messages' => $failureMessages,
        'checks' => $checks,
        'updated_at' => $row['updated_at'] ?? null,
    ];
}

function mg_crqa_history_row(array $row): array
{
    $walletId = (string)$row['wallet_item_id'];
    $customerName = trim((string)($row['customer_name'] ?? ''));
    $customerEmail = strtolower(trim((string)($row['customer_email'] ?? '')));
    return [
        'wallet_item_id' => $walletId,
        'wallet_status' => (string)$row['wallet_status'],
        'title' => (string)($row['title_snapshot'] ?: $row['reward_template_title'] ?: 'Customer Refund voucher'),
        'campaign_id' => (string)$row['campaign_public_id'],
        'campaign_title' => (string)$row['campaign_title'],
        'contact_id' => (string)($row['contact_public_id'] ?? ''),
        'customer_name' => $customerName !== '' ? $customerName : 'Customer',
        'customer_email' => $customerEmail,
        'customer_user_id' => (int)($row['customer_user_id'] ?? 0),
        'value' => mg_crqa_money((int)($row['value_cents_snapshot'] ?? 0), (string)($row['currency_snapshot'] ?? 'USD')),
        'issued_at' => $row['issued_at'] ?? null,
        'claimed_at' => $row['claimed_at'] ?? null,
        'redeemed_at' => $row['redeemed_at'] ?? null,
        'expires_at' => $row['expires_at'] ?? null,
        'action_url' => '/merchant-notifications.php?filter=rewards&item=' . rawurlencode($walletId),
        'customer_url' => $row['contact_public_id'] ? '/merchant-customer.php?campaign_contact_id=' . rawurlencode((string)$row['contact_public_id']) : '',
    ];
}

mg_require_method('GET');
$user = mg_require_permission('merchant.campaigns.view');
$merchantId = (int)$user['id'];
$pdo = mg_db();
mg_merchant_ensure_workspace($pdo, $user);

try {
    $campaignSql = "SELECT c.public_id,c.title,c.status,c.quantity_limit,c.issued_count,c.starts_at,c.ends_at,c.updated_at,
            rt.public_id reward_template_public_id,rt.title reward_template_title,rt.status reward_status,rt.quantity_limit reward_quantity_limit,rt.issued_count reward_issued_count,rt.value_amount_cents,rt.currency,
            COUNT(DISTINCT wi.id) wallet_issued,
            COUNT(DISTINCT CASE WHEN wi.status='claimed' THEN wi.id END) wallet_claimed,
            COUNT(DISTINCT CASE WHEN wi.status='redeemed' THEN wi.id END) wallet_redeemed
        FROM campaigns c
        LEFT JOIN reward_templates rt ON rt.id=c.reward_template_id
        LEFT JOIN wallet_items wi ON wi.campaign_id=c.id AND wi.source_type='customer_refund'
        WHERE c.merchant_user_id=? AND c.campaign_type='customer_refund' AND c.status<>'archived'
        GROUP BY c.id
        ORDER BY FIELD(c.status,'active','draft','paused','ended'), c.updated_at DESC, c.id DESC
        LIMIT 100";
    $stmt = $pdo->prepare($campaignSql);
    $stmt->execute([$merchantId]);
    $campaigns = array_map('mg_crqa_campaign_row', $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);

    $historySql = "SELECT wi.public_id wallet_item_id,wi.status wallet_status,wi.title_snapshot,wi.value_cents_snapshot,wi.currency_snapshot,wi.issued_at,wi.claimed_at,wi.redeemed_at,wi.expires_at,
            c.public_id campaign_public_id,c.title campaign_title,
            cc.public_id contact_public_id,cc.name customer_name,cc.email customer_email,cc.user_id customer_user_id,
            rt.title reward_template_title
        FROM wallet_items wi
        INNER JOIN campaigns c ON c.id=wi.campaign_id
        LEFT JOIN campaign_contacts cc ON cc.id=wi.contact_id
        LEFT JOIN reward_templates rt ON rt.id=wi.reward_template_id
        WHERE wi.merchant_user_id=? AND c.campaign_type='customer_refund' AND wi.source_type='customer_refund'
        ORDER BY wi.issued_at DESC, wi.id DESC
        LIMIT 100";
    $historyStmt = $pdo->prepare($historySql);
    $historyStmt->execute([$merchantId]);
    $history = array_map('mg_crqa_history_row', $historyStmt->fetchAll(PDO::FETCH_ASSOC) ?: []);

    $totals = ['campaigns' => count($campaigns), 'send_ready' => 0, 'needs_attention' => 0, 'sent' => count($history), 'issued' => 0, 'claimed' => 0, 'redeemed' => 0];
    foreach ($campaigns as $campaign) {
        if (!empty($campaign['send_ready'])) $totals['send_ready']++; else $totals['needs_attention']++;
    }
    foreach ($history as $item) {
        $status = (string)$item['wallet_status'];
        if ($status === 'issued' || $status === 'viewed') $totals['issued']++;
        if ($status === 'claimed') $totals['claimed']++;
        if ($status === 'redeemed') $totals['redeemed']++;
    }

    mg_ok([
        'campaigns' => $campaigns,
        'send_history' => $history,
        'totals' => $totals,
        'todo' => [
            'customer_refund_invite_by_email' => 'Planned future enhancement; not enabled because outbound email sending is not active yet.',
        ],
        'schema_ready' => true,
    ], 'Customer Refund QA loaded.');
} catch (Throwable $error) {
    mg_security_log('warning', 'merchant.customer_refund_qa.unavailable', 'Customer Refund QA unavailable.', ['exception_class' => $error::class, 'message' => $error->getMessage()], $merchantId);
    mg_ok(['campaigns' => [], 'send_history' => [], 'totals' => ['campaigns'=>0,'send_ready'=>0,'needs_attention'=>0,'sent'=>0,'issued'=>0,'claimed'=>0,'redeemed'=>0], 'todo' => ['customer_refund_invite_by_email' => 'Planned future enhancement; not enabled because outbound email sending is not active yet.'], 'schema_ready' => false], 'Customer Refund QA unavailable until campaign schema is installed.');
}
