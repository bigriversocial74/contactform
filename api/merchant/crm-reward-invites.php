<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';
require_once dirname(__DIR__, 2) . '/includes/merchant-crm-reward-invites.php';

function mg_crm_reward_invite_row(array $row): array
{
    $status = (string)$row['status'];
    if ($status === 'sent' && !empty($row['expires_at']) && strtotime((string)$row['expires_at']) < time()) $status = 'expired';
    return [
        'id' => (string)$row['public_id'],
        'status' => $status,
        'email' => (string)$row['email'],
        'name' => (string)($row['name'] ?? ''),
        'contact_id' => (string)($row['contact_public_id'] ?? ''),
        'campaign_id' => (string)($row['campaign_public_id'] ?? ''),
        'campaign_title' => (string)($row['campaign_title'] ?? ''),
        'reward_template_id' => (string)($row['template_public_id'] ?? ''),
        'reward_title' => (string)($row['reward_title'] ?? ''),
        'value_cents' => (int)($row['value_amount_cents'] ?? 0),
        'currency' => (string)($row['currency'] ?? 'USD'),
        'wallet_item_id' => (string)($row['wallet_public_id'] ?? ''),
        'invite_url' => (string)($row['invite_url'] ?? ''),
        'sent_at' => $row['sent_at'] ?? null,
        'linked_at' => $row['linked_at'] ?? null,
        'delivered_at' => $row['delivered_at'] ?? null,
        'expires_at' => $row['expires_at'] ?? null,
        'created_at' => $row['created_at'] ?? null,
        'updated_at' => $row['updated_at'] ?? null,
    ];
}

mg_require_method('GET');
$user = mg_require_permission('merchant.campaigns.view');
$merchantId = (int)$user['id'];
$pdo = mg_db();
mg_merchant_ensure_workspace($pdo, $user);
$status = strtolower(trim((string)($_GET['status'] ?? '')));
$contactRef = strtolower(trim((string)($_GET['contact_id'] ?? $_GET['contact'] ?? '')));
$limit = max(1, min(250, (int)($_GET['limit'] ?? 100)));

try {
    if (!mg_crm_reward_invites_ready($pdo)) mg_ok(['invites' => [], 'totals' => ['sent' => 0, 'linked' => 0, 'delivered' => 0, 'revoked' => 0, 'expired' => 0], 'schema_ready' => false]);
    $sql = "SELECT i.*,cc.public_id contact_public_id,c.public_id campaign_public_id,c.title campaign_title,rt.public_id template_public_id,rt.title reward_title,rt.value_amount_cents,rt.currency,wi.public_id wallet_public_id FROM crm_reward_invites i INNER JOIN campaign_contacts cc ON cc.id=i.contact_id INNER JOIN campaigns c ON c.id=i.campaign_id INNER JOIN reward_templates rt ON rt.id=i.reward_template_id LEFT JOIN wallet_items wi ON wi.id=i.wallet_item_id WHERE i.merchant_user_id=?";
    $params = [$merchantId];
    if ($status !== '') { $sql .= " AND i.status=?"; $params[] = $status; }
    if ($contactRef !== '') { $sql .= " AND cc.public_id=?"; $params[] = $contactRef; }
    $sql .= ' ORDER BY i.created_at DESC,i.id DESC LIMIT ' . $limit;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $invites = array_map('mg_crm_reward_invite_row', $stmt->fetchAll(PDO::FETCH_ASSOC));
    $totals = ['sent' => 0, 'linked' => 0, 'delivered' => 0, 'revoked' => 0, 'expired' => 0];
    foreach ($invites as $invite) { $totals[$invite['status']] = ($totals[$invite['status']] ?? 0) + 1; }
    $conversion = ($totals['sent'] + $totals['linked'] + $totals['delivered']) > 0 ? round((($totals['linked'] + $totals['delivered']) / ($totals['sent'] + $totals['linked'] + $totals['delivered'])) * 100, 1) : 0;
    mg_ok(['invites' => $invites, 'totals' => $totals, 'conversion_rate' => $conversion, 'count' => count($invites), 'schema_ready' => true]);
} catch (Throwable $error) {
    mg_security_log('warning', 'merchant.crm_reward_invites.failed', 'Unable to load CRM reward invites.', ['exception_class' => $error::class, 'message' => $error->getMessage()], $merchantId);
    mg_ok(['invites' => [], 'totals' => ['sent' => 0, 'linked' => 0, 'delivered' => 0, 'revoked' => 0, 'expired' => 0], 'conversion_rate' => 0, 'count' => 0, 'schema_ready' => false], 'CRM reward invites unavailable.');
}
