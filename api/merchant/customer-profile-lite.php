<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';

function mg_cpl_param(string $key): string { return trim((string)($_GET[$key] ?? '')); }
function mg_cpl_money(int $cents, string $currency = 'USD'): string { return strtoupper($currency ?: 'USD') . ' ' . number_format($cents / 100, 2); }
function mg_cpl_label(string $value): string { return ucwords(str_replace(['_', '-'], ' ', $value)); }
function mg_cpl_initials(string $name, string $email = ''): string
{
    $base = trim($name) ?: trim($email);
    $parts = preg_split('/\s+/', $base) ?: [];
    $out = '';
    foreach ($parts as $part) { $out .= mb_substr((string)$part, 0, 1); if (mb_strlen($out) >= 2) break; }
    return strtoupper($out ?: 'C');
}
function mg_cpl_table_exists(PDO $pdo, string $table): bool
{
    static $cache = [];
    if (isset($cache[$table])) return $cache[$table];
    try {
        $stmt = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1');
        $stmt->execute([$table]);
        return $cache[$table] = (bool)$stmt->fetchColumn();
    } catch (Throwable) { return $cache[$table] = false; }
}

mg_require_method('GET');
$user = mg_require_permission('merchant.campaigns.view');
$merchantId = (int)$user['id'];
$pdo = mg_db();
mg_merchant_ensure_workspace($pdo, $user);

$campaignContactId = strtolower(mg_cpl_param('campaign_contact_id') ?: mg_cpl_param('contact_id') ?: mg_cpl_param('id'));
$email = strtolower(mg_cpl_param('email'));
if ($campaignContactId === '' && $email === '') mg_fail('Campaign contact id or email is required.', 422);
if ($campaignContactId !== '' && preg_match('/^[0-9a-f-]{36}$/i', $campaignContactId) !== 1) mg_fail('A valid campaign contact id is required.', 422);

try {
    $where = ['cc.merchant_user_id=?'];
    $params = [$merchantId];
    if ($campaignContactId !== '') { $where[] = 'cc.public_id=?'; $params[] = $campaignContactId; }
    elseif ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) { $where[] = 'LOWER(cc.email)=?'; $params[] = $email; }
    else mg_fail('A valid customer lookup is required.', 422);

    $sql = "SELECT cc.*,c.public_id campaign_public_id,c.title campaign_title,c.campaign_type,
                   COALESCE(cc.user_id,email_user.id) resolved_user_id
            FROM campaign_contacts cc
            INNER JOIN campaigns c ON c.id=cc.campaign_id
            LEFT JOIN users email_user ON cc.user_id IS NULL AND LOWER(email_user.email)=LOWER(cc.email)
            WHERE " . implode(' AND ', $where) . "
            ORDER BY cc.updated_at DESC,cc.id DESC
            LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) mg_fail('Customer profile not found for this merchant.', 404);

    $walletCount = 0;
    $claimedCount = 0;
    $redeemedCount = 0;
    $openWalletCount = 0;
    $openWalletValue = 0;
    $lastRewardTitle = '—';
    if (mg_cpl_table_exists($pdo, 'wallet_items')) {
        try {
            $walletStmt = $pdo->prepare("SELECT status,title_snapshot,value_cents_snapshot,currency_snapshot,issued_at,claimed_at,redeemed_at FROM wallet_items WHERE merchant_user_id=? AND contact_id=? ORDER BY issued_at DESC,id DESC LIMIT 50");
            $walletStmt->execute([$merchantId, (int)$row['id']]);
            foreach ($walletStmt->fetchAll(PDO::FETCH_ASSOC) as $index => $wallet) {
                $walletCount++;
                $status = (string)($wallet['status'] ?? '');
                if (in_array($status, ['claimed','redeemed'], true)) $claimedCount++;
                if ($status === 'redeemed') $redeemedCount++;
                if (in_array($status, ['issued','viewed','claimed'], true)) { $openWalletCount++; $openWalletValue += (int)($wallet['value_cents_snapshot'] ?? 0); }
                if ($index === 0) $lastRewardTitle = (string)($wallet['title_snapshot'] ?: 'Reward');
            }
        } catch (Throwable) {}
    }

    $resolvedUserId = (int)($row['resolved_user_id'] ?? $row['user_id'] ?? 0);
    $campaignPublicId = (string)($row['campaign_public_id'] ?? '');
    $campaignContactPublicId = (string)$row['public_id'];
    $name = (string)($row['name'] ?: $row['email'] ?: 'Customer');
    $customerEmail = strtolower((string)($row['email'] ?? ''));
    $source = (string)($row['source'] ?? $row['campaign_type'] ?? 'campaign_contact');
    $lastActivity = $row['updated_at'] ?? $row['created_at'] ?? null;
    $redemptionRate = $walletCount > 0 ? (int)round(($claimedCount / $walletCount) * 100) : 0;

    mg_ok([
        'customer' => [
            'id' => 'campaign-' . $campaignContactPublicId,
            'crm_contact_id' => '',
            'campaign_contact_id' => $campaignContactPublicId,
            'campaign_contact_ids' => [$campaignContactPublicId],
            'user_id' => $resolvedUserId,
            'name' => $name,
            'email' => $customerEmail,
            'phone' => (string)($row['phone'] ?? ''),
            'initials' => mg_cpl_initials($name, $customerEmail),
            'status' => 'active',
            'stage' => $resolvedUserId > 0 ? 'customer' : 'lead',
            'tags' => array_values(array_filter([$source, $resolvedUserId > 0 ? 'account' : 'no_account'])),
            'first_seen_at' => $row['created_at'] ?? null,
            'last_activity_at' => $lastActivity,
            'source_campaign' => (string)($row['campaign_title'] ?: mg_cpl_label($source)),
            'preferred_location' => '—',
        ],
        'action_ids' => [
            'crm_contact_id' => '',
            'contact_id' => '',
            'campaign_contact_id' => $campaignContactPublicId,
            'campaign_contact_ids' => [$campaignContactPublicId],
            'customer_user_id' => $resolvedUserId,
            'user_id' => $resolvedUserId,
            'email' => $customerEmail,
        ],
        'actions' => [
            'can_message' => true,
            'can_send_reward' => $resolvedUserId > 0,
            'can_followup' => true,
            'can_add_note' => false,
            'message_endpoint' => '/api/merchant/crm-message.php',
            'reward_endpoint' => '/api/merchant/crm-send-gift.php',
            'followup_endpoint' => '/api/merchant/crm-followup.php',
            'note_endpoint' => '/api/merchant/customer-profile.php',
        ],
        'links' => [
            'customer_profile' => '/merchant-customer.php?campaign_contact_id=' . rawurlencode($campaignContactPublicId),
            'message' => '/merchant-crm.php?tab=contacts&action=message&campaign_contact_id=' . rawurlencode($campaignContactPublicId),
            'send_reward' => '/merchant-crm.php?tab=contacts&action=reward&campaign_contact_id=' . rawurlencode($campaignContactPublicId),
            'followup' => '/merchant-crm.php?tab=contacts&action=followup&campaign_contact_id=' . rawurlencode($campaignContactPublicId),
            'notes' => '',
        ],
        'metrics' => [
            'wallet_rewards_received' => $walletCount,
            'claimed_rewards' => $claimedCount,
            'open_wallet_items' => $openWalletCount,
            'open_wallet_value' => mg_cpl_money($openWalletValue),
            'tips_total' => mg_cpl_money(0),
            'tip_count' => 0,
            'redemption_rate' => $redemptionRate,
            'estimated_customer_value' => mg_cpl_money($openWalletValue),
        ],
        'snapshot' => [
            'last_reward' => $lastRewardTitle,
            'current_open_wallet_item' => $openWalletCount > 0 ? $openWalletCount . ' open wallet item' . ($openWalletCount === 1 ? '' : 's') : 'No open wallet items',
            'last_claim_location' => '—',
            'favorite_campaign_type' => mg_cpl_label((string)($row['campaign_type'] ?? $source)),
            'average_redemption_delay' => '—',
        ],
        'activity_chart' => [],
        'messages' => [],
        'rewards' => [],
        'tips' => [],
        'campaign_sources' => [[
            'campaign' => (string)($row['campaign_title'] ?: 'Campaign'),
            'campaign_id' => $campaignPublicId,
            'type' => (string)($row['campaign_type'] ?? $source),
            'first_seen' => $row['created_at'] ?? null,
            'last_seen' => $lastActivity,
            'interactions' => 1,
            'action_url' => $campaignPublicId !== '' ? '/merchant-campaigns.php?campaign=' . rawurlencode($campaignPublicId) : '',
        ]],
        'notes' => [],
        'redemptions' => [],
        'timeline' => [[
            'id' => $campaignContactPublicId,
            'type' => 'campaign_contact',
            'title' => 'Campaign contact loaded',
            'body' => (string)($row['campaign_title'] ?: mg_cpl_label($source)),
            'at' => $lastActivity,
            'icon' => '➤',
            'tone' => 'is-blue',
            'object_id' => $campaignContactPublicId,
            'action_url' => $campaignPublicId !== '' ? '/merchant-campaigns.php?campaign=' . rawurlencode($campaignPublicId) : '',
        ]],
        'debug' => ['lite_profile' => true, 'profile_version' => 1],
    ], 'Loaded basic customer profile.');
} catch (Throwable $error) {
    mg_security_log('error', 'merchant.customer_profile_lite.failed', 'Unable to load lite customer profile.', ['exception_class'=>$error::class,'message'=>$error->getMessage()], $merchantId);
    mg_fail('Unable to load this customer profile.', 500);
}
