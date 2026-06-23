<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';

function mg_wallet_lookup_row(array $row): array
{
    $status = (string) $row['status'];
    $expired = !empty($row['expires_at']) && strtotime((string) $row['expires_at']) < time() && !in_array($status, ['redeemed','cancelled'], true);
    if ($expired) $status = 'expired';
    return [
        'id' => (string) $row['public_id'],
        'title' => (string) $row['title_snapshot'],
        'status' => $status,
        'is_expired' => $expired,
        'value_cents' => (int) $row['value_cents_snapshot'],
        'currency' => (string) $row['currency_snapshot'],
        'source_type' => (string) $row['source_type'],
        'campaign_title' => $row['campaign_title'] ?? null,
        'campaign_type' => $row['campaign_type'] ?? null,
        'reward_template_title' => $row['reward_template_title'] ?? null,
        'customer_name' => $row['contact_name'] ?? null,
        'customer_email' => $row['contact_email'] ?? null,
        'redemption_instructions' => $row['redemption_instructions'] ?? null,
        'issued_at' => $row['issued_at'] ?? null,
        'claimed_at' => $row['claimed_at'] ?? null,
        'redeemed_at' => $row['redeemed_at'] ?? null,
        'expires_at' => $row['expires_at'] ?? null,
        'can_complete' => $status === 'claimed',
    ];
}

mg_require_method('GET');
$user = mg_require_permission('merchant.campaigns.manage');
$merchantId = (int) $user['id'];
$pdo = mg_db();
mg_merchant_ensure_workspace($pdo, $user);
$walletId = strtolower(trim((string) ($_GET['wallet_item_id'] ?? $_GET['id'] ?? '')));

if ($walletId === '' || strlen($walletId) !== 36 || !preg_match('/^[a-f0-9-]{36}$/', $walletId)) {
    mg_fail('Invalid wallet item.', 422);
}

try {
    $stmt = $pdo->prepare('SELECT wi.*, rt.title reward_template_title, rt.redemption_instructions, c.title campaign_title, c.campaign_type, cc.name contact_name, cc.email contact_email
        FROM wallet_items wi
        LEFT JOIN reward_templates rt ON rt.id = wi.reward_template_id
        LEFT JOIN campaigns c ON c.id = wi.campaign_id
        LEFT JOIN campaign_contacts cc ON cc.id = wi.contact_id
        WHERE wi.public_id = ? AND wi.merchant_user_id = ?
        LIMIT 1');
    $stmt->execute([$walletId, $merchantId]);
    $row = $stmt->fetch();
    if (!$row) mg_fail('Wallet item not found.', 404);
    mg_ok(['wallet_item' => mg_wallet_lookup_row($row), 'schema_ready' => true]);
} catch (Throwable $error) {
    mg_security_log('warning', 'merchant.wallet_lookup.unavailable', 'Wallet lookup unavailable.', ['exception_class' => $error::class], $merchantId);
    mg_fail('Wallet lookup unavailable.', 500);
}
