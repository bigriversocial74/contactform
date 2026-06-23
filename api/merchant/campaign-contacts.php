<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';

function mg_campaign_contact_row(array $row): array
{
    return [
        'id' => (string) $row['public_id'],
        'campaign_id' => (string) ($row['campaign_public_id'] ?? ''),
        'campaign_title' => (string) ($row['campaign_title'] ?? ''),
        'email' => (string) $row['email'],
        'phone' => (string) ($row['phone'] ?? ''),
        'name' => (string) ($row['name'] ?? ''),
        'source' => (string) $row['source'],
        'opt_in_status' => (string) $row['opt_in_status'],
        'wallet_count' => (int) ($row['wallet_count'] ?? 0),
        'claimed_count' => (int) ($row['claimed_count'] ?? 0),
        'redeemed_count' => (int) ($row['redeemed_count'] ?? 0),
        'winner_count' => (int) ($row['winner_count'] ?? 0),
        'created_at' => $row['created_at'] ?? null,
        'updated_at' => $row['updated_at'] ?? null,
    ];
}

mg_require_method('GET');
$user = mg_require_permission('merchant.campaigns.view');
$merchantId = (int) $user['id'];
$pdo = mg_db();
mg_merchant_ensure_workspace($pdo, $user);
$campaignPublicId = strtolower(trim((string) ($_GET['campaign_id'] ?? '')));

try {
    $sql = 'SELECT cc.*, c.public_id campaign_public_id, c.title campaign_title,
                   COUNT(DISTINCT wi.id) wallet_count,
                   COUNT(DISTINCT CASE WHEN wi.status = \'claimed\' THEN wi.id END) claimed_count,
                   COUNT(DISTINCT CASE WHEN wi.status = \'redeemed\' THEN wi.id END) redeemed_count,
                   COUNT(DISTINCT CASE WHEN wi.source_type = \'contest_winner\' THEN wi.id END) winner_count
            FROM campaign_contacts cc
            INNER JOIN campaigns c ON c.id = cc.campaign_id
            LEFT JOIN wallet_items wi ON wi.contact_id = cc.id
            WHERE cc.merchant_user_id = ?';
    $params = [$merchantId];
    if ($campaignPublicId !== '') {
        $sql .= ' AND c.public_id = ?';
        $params[] = $campaignPublicId;
    }
    $sql .= ' GROUP BY cc.id, c.public_id, c.title ORDER BY cc.updated_at DESC, cc.id DESC LIMIT 200';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $contacts = array_map('mg_campaign_contact_row', $stmt->fetchAll());
    mg_ok(['contacts' => $contacts, 'count' => count($contacts), 'schema_ready' => true]);
} catch (Throwable $error) {
    mg_security_log('warning', 'merchant.campaign_contacts.unavailable', 'Campaign contacts unavailable.', ['exception_class' => $error::class], $merchantId);
    mg_ok(['contacts' => [], 'count' => 0, 'schema_ready' => false], 'Campaign contacts unavailable until the Stage 12 schema is installed.');
}
