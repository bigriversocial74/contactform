<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

function mg_wallet_item_public(array $row): array
{
    $status = (string) $row['status'];
    $expired = !empty($row['expires_at']) && strtotime((string) $row['expires_at']) < time() && !in_array($status, ['redeemed','cancelled'], true);
    if ($expired) $status = 'expired';
    $canClaim = in_array($status, ['issued','viewed'], true);
    $canComplete = $status === 'claimed';
    $displayValue = ((string) ($row['value_cents_snapshot'] ?? '0')) !== '0'
        ? ((string) ($row['currency_snapshot'] ?? 'USD')) . ' ' . number_format(((int) $row['value_cents_snapshot']) / 100, 2)
        : 'Reward';
    return [
        'id' => (string) $row['public_id'],
        'title' => (string) $row['title_snapshot'],
        'status' => $status,
        'is_expired' => $expired,
        'source_type' => (string) $row['source_type'],
        'value_cents' => (int) $row['value_cents_snapshot'],
        'currency' => (string) $row['currency_snapshot'],
        'display_value' => $displayValue,
        'merchant_user_id' => (int) $row['merchant_user_id'],
        'reward_template_title' => $row['reward_template_title'] ?? null,
        'campaign_title' => $row['campaign_title'] ?? null,
        'campaign_type' => $row['campaign_type'] ?? null,
        'merchant_label' => $row['merchant_label'] ?? null,
        'redemption_instructions' => $row['redemption_instructions'] ?? null,
        'can_claim' => $canClaim,
        'can_complete' => $canComplete,
        'timeline' => [
            'issued_at' => $row['issued_at'] ?? null,
            'viewed_at' => $row['viewed_at'] ?? null,
            'claimed_at' => $row['claimed_at'] ?? null,
            'redeemed_at' => $row['redeemed_at'] ?? null,
            'expires_at' => $row['expires_at'] ?? null,
        ],
    ];
}

mg_require_method('GET');
$user = mg_require_api_user();
$pdo = mg_db();
$userId = (int) $user['id'];
$email = strtolower(trim((string) ($user['email'] ?? '')));
$status = trim((string) ($_GET['status'] ?? 'all'));
$allowed = ['issued','viewed','claimed','redeemed','expired','cancelled'];

try {
    $sql = 'SELECT wi.*, rt.title reward_template_title, rt.redemption_instructions, c.title campaign_title, c.campaign_type, u.display_name merchant_label, cc.email contact_email
            FROM wallet_items wi
            LEFT JOIN reward_templates rt ON rt.id = wi.reward_template_id
            LEFT JOIN campaigns c ON c.id = wi.campaign_id
            LEFT JOIN users u ON u.id = wi.merchant_user_id
            LEFT JOIN campaign_contacts cc ON cc.id = wi.contact_id
            WHERE (wi.user_id = ? OR cc.email = ? OR wi.source_id = ?)';
    $params = [$userId, $email, $email];
    if (in_array($status, $allowed, true)) {
        if ($status === 'expired') {
            $sql .= ' AND (wi.status = ? OR (wi.expires_at IS NOT NULL AND wi.expires_at < NOW() AND wi.status NOT IN (\'redeemed\',\'cancelled\')))';
            $params[] = $status;
        } else {
            $sql .= ' AND wi.status = ?';
            $params[] = $status;
        }
    }
    $sql .= ' ORDER BY wi.updated_at DESC, wi.id DESC LIMIT 100';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $items = array_map('mg_wallet_item_public', $stmt->fetchAll());

    $totals = ['all' => count($items), 'issued' => 0, 'viewed' => 0, 'claimed' => 0, 'redeemed' => 0, 'expired' => 0, 'cancelled' => 0, 'claimable' => 0];
    foreach ($items as $item) {
        if (isset($totals[$item['status']])) $totals[$item['status']]++;
        if ($item['can_claim']) $totals['claimable']++;
    }

    mg_ok(['items' => $items, 'totals' => $totals, 'schema_ready' => true]);
} catch (Throwable $error) {
    mg_security_log('warning', 'account.wallet_items.unavailable', 'Wallet items unavailable.', ['exception_class' => $error::class], $userId);
    mg_ok(['items' => [], 'totals' => ['all'=>0,'issued'=>0,'viewed'=>0,'claimed'=>0,'redeemed'=>0,'expired'=>0,'cancelled'=>0,'claimable'=>0], 'schema_ready' => false], 'Wallet items unavailable until the Stage 12 schema is installed.');
}
