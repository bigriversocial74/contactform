<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';

function mg_activity_row(array $row): array
{
    return [
        'id' => (string) $row['public_id'],
        'title' => (string) $row['title'],
        'campaign_type' => (string) $row['campaign_type'],
        'status' => (string) $row['status'],
        'reward_template_title' => $row['reward_template_title'] ?? null,
        'contacts_count' => (int) ($row['contacts_count'] ?? 0),
        'wallet_issued_count' => (int) ($row['wallet_issued_count'] ?? 0),
        'wallet_claimed_count' => (int) ($row['wallet_claimed_count'] ?? 0),
        'wallet_redeemed_count' => (int) ($row['wallet_redeemed_count'] ?? 0),
        'events_count' => (int) ($row['events_count'] ?? 0),
        'updated_at' => $row['updated_at'] ?? null,
    ];
}

mg_require_method('GET');
$user = mg_require_permission('merchant.campaigns.view');
$merchantId = (int) $user['id'];
$pdo = mg_db();
mg_merchant_ensure_workspace($pdo, $user);

try {
    $stmt = $pdo->prepare('SELECT c.public_id,c.title,c.campaign_type,c.status,c.updated_at,rt.title reward_template_title,
        COUNT(DISTINCT cc.id) contacts_count,
        COUNT(DISTINCT wi.id) wallet_issued_count,
        COUNT(DISTINCT CASE WHEN wi.status = \'claimed\' THEN wi.id END) wallet_claimed_count,
        COUNT(DISTINCT CASE WHEN wi.status = \'redeemed\' THEN wi.id END) wallet_redeemed_count,
        COUNT(DISTINCT ce.id) events_count
        FROM campaigns c
        LEFT JOIN reward_templates rt ON rt.id = c.reward_template_id
        LEFT JOIN campaign_contacts cc ON cc.campaign_id = c.id
        LEFT JOIN wallet_items wi ON wi.campaign_id = c.id
        LEFT JOIN campaign_events ce ON ce.campaign_id = c.id
        WHERE c.merchant_user_id = ?
        GROUP BY c.id, c.public_id, c.title, c.campaign_type, c.status, c.updated_at, rt.title
        ORDER BY c.updated_at DESC, c.id DESC
        LIMIT 100');
    $stmt->execute([$merchantId]);
    $campaigns = array_map('mg_activity_row', $stmt->fetchAll());

    $totals = [
        'campaigns' => count($campaigns),
        'contacts' => array_sum(array_column($campaigns, 'contacts_count')),
        'wallet_issued' => array_sum(array_column($campaigns, 'wallet_issued_count')),
        'wallet_claimed' => array_sum(array_column($campaigns, 'wallet_claimed_count')),
        'wallet_redeemed' => array_sum(array_column($campaigns, 'wallet_redeemed_count')),
        'events' => array_sum(array_column($campaigns, 'events_count')),
    ];

    mg_ok(['campaigns' => $campaigns, 'totals' => $totals, 'schema_ready' => true]);
} catch (Throwable $error) {
    mg_security_log('warning', 'merchant.campaign_activity.schema_unavailable', 'Campaign activity schema is unavailable.', ['exception_class' => $error::class], $merchantId);
    mg_ok(['campaigns' => [], 'totals' => ['campaigns'=>0,'contacts'=>0,'wallet_issued'=>0,'wallet_claimed'=>0,'wallet_redeemed'=>0,'events'=>0], 'schema_ready' => false], 'Campaign activity unavailable until the Stage 12 schema is installed.');
}
