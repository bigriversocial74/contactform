<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

function mg_wallet_canonical_status(array $row): string
{
    $microgift = trim((string)($row['microgift_status'] ?? ''));
    if ($microgift !== '') {
        if (in_array($microgift, ['issued','delivered','claim_pending'], true)) return 'issued';
        if (in_array($microgift, ['claimed','redeemable'], true)) return 'claimed';
        if ($microgift === 'redeemed') return 'redeemed';
        if ($microgift === 'expired') return 'expired';
        if (in_array($microgift, ['cancelled','revoked','replaced'], true)) return 'cancelled';
    }
    return (string)$row['status'];
}

function mg_wallet_item_public(array $row): array
{
    $status = mg_wallet_canonical_status($row);
    $expiresAt = $row['microgift_expires_at'] ?? $row['expires_at'] ?? null;
    $expired = !empty($expiresAt) && strtotime((string)$expiresAt) < time() && !in_array($status, ['redeemed','cancelled'], true);
    if ($expired) $status = 'expired';
    $canClaim = in_array($status, ['issued','viewed'], true);
    $canComplete = $status === 'claimed';
    $displayValue = ((string)($row['value_cents_snapshot'] ?? '0')) !== '0'
        ? ((string)($row['currency_snapshot'] ?? 'USD')) . ' ' . number_format(((int)$row['value_cents_snapshot']) / 100, 2)
        : 'Reward';
    $actionUrl = !empty($row['action_item_id']) ? '/inbox.php?item=' . rawurlencode((string)$row['action_item_id']) : null;
    if ($status === 'claimed' && !empty($row['action_item_id'])) $actionUrl = '/claimed.php?item=' . rawurlencode((string)$row['action_item_id']);
    return [
        'id' => (string)$row['public_id'],
        'title' => (string)$row['title_snapshot'],
        'status' => $status,
        'is_expired' => $expired,
        'source_type' => (string)$row['source_type'],
        'value_cents' => (int)$row['value_cents_snapshot'],
        'currency' => (string)$row['currency_snapshot'],
        'display_value' => $displayValue,
        'merchant_user_id' => (int)$row['merchant_user_id'],
        'reward_template_title' => $row['reward_template_title'] ?? null,
        'campaign_title' => $row['campaign_title'] ?? null,
        'campaign_type' => $row['campaign_type'] ?? null,
        'merchant_label' => $row['merchant_label'] ?? null,
        'redemption_instructions' => $row['redemption_instructions'] ?? null,
        'pppm_item_id' => $row['pppm_public_id'] ?? null,
        'microgift_instance_id' => $row['microgift_instance_id'] ?? null,
        'action_item_id' => $row['action_item_id'] ?? null,
        'action_folder' => $row['action_folder'] ?? null,
        'action_state' => $row['action_state'] ?? null,
        'action_url' => $actionUrl,
        'can_claim' => $canClaim,
        'can_complete' => $canComplete,
        'timeline' => [
            'issued_at' => $row['issued_at'] ?? null,
            'viewed_at' => $row['viewed_at'] ?? null,
            'claimed_at' => $row['microgift_claimed_at'] ?? $row['claimed_at'] ?? null,
            'redeemed_at' => $row['microgift_redeemed_at'] ?? $row['redeemed_at'] ?? null,
            'expires_at' => $expiresAt,
        ],
    ];
}

mg_require_method('GET');
$user = mg_require_api_user();
$pdo = mg_db();
$userId = (int)$user['id'];
$email = strtolower(trim((string)($user['email'] ?? '')));
$status = trim((string)($_GET['status'] ?? 'all'));
$allowed = ['issued','viewed','claimed','redeemed','expired','cancelled'];

try {
    $sql = 'SELECT wi.*, rt.title reward_template_title, rt.redemption_instructions, c.title campaign_title, c.campaign_type, u.display_name merchant_label, cc.email contact_email,
                   p.public_id pppm_public_id,
                   mi.public_id microgift_instance_id, mi.status microgift_status, mi.claimed_at microgift_claimed_at, mi.redeemed_at microgift_redeemed_at, mi.expires_at microgift_expires_at,
                   ac.public_id action_item_id, ac.folder action_folder, ac.state action_state
            FROM wallet_items wi
            LEFT JOIN reward_templates rt ON rt.id = wi.reward_template_id
            LEFT JOIN campaigns c ON c.id = wi.campaign_id
            LEFT JOIN users u ON u.id = wi.merchant_user_id
            LEFT JOIN campaign_contacts cc ON cc.id = wi.contact_id
            LEFT JOIN pppm_items p ON p.id = wi.pppm_item_id
            LEFT JOIN microgift_instances mi ON mi.pppm_item_id = wi.pppm_item_id
            LEFT JOIN microgift_inbox_items ac ON ac.instance_id = mi.id AND ac.user_id = ? AND ac.archived_at IS NULL
            WHERE (wi.user_id = ? OR cc.email = ? OR wi.source_id = ?)';
    $params = [$userId, $userId, $email, $email];
    if (in_array($status, $allowed, true)) {
        if ($status === 'expired') {
            $sql .= ' AND (wi.status = ? OR mi.status = ? OR (COALESCE(mi.expires_at, wi.expires_at) IS NOT NULL AND COALESCE(mi.expires_at, wi.expires_at) < NOW() AND COALESCE(mi.status, wi.status) NOT IN (\'redeemed\',\'cancelled\')))';
            $params[] = $status;
            $params[] = $status;
        } else {
            $sql .= ' AND (wi.status = ? OR mi.status = ?)';
            $params[] = $status;
            $params[] = $status === 'claimed' ? 'redeemable' : $status;
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

    mg_ok(['items' => $items, 'totals' => $totals, 'schema_ready' => true, 'canonical_source' => 'wallet_to_pppm_action_center']);
} catch (Throwable $error) {
    mg_security_log('warning', 'account.wallet_items.unavailable', 'Wallet items unavailable.', ['exception_class' => $error::class, 'message' => $error->getMessage()], $userId);
    mg_ok(['items' => [], 'totals' => ['all'=>0,'issued'=>0,'viewed'=>0,'claimed'=>0,'redeemed'=>0,'expired'=>0,'cancelled'=>0,'claimable'=>0], 'schema_ready' => false], 'Wallet items unavailable until the Stage 12 schema is installed.');
}
