<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';

function mg_campaign_event_row(array $row): array
{
    return [
        'id' => (string) $row['public_id'],
        'campaign_id' => $row['campaign_public_id'] ?? null,
        'campaign_title' => $row['campaign_title'] ?? null,
        'wallet_item_id' => $row['wallet_item_public_id'] ?? null,
        'contact_id' => $row['contact_public_id'] ?? null,
        'contact_email' => $row['contact_email'] ?? null,
        'event_type' => (string) $row['event_type'],
        'event_context' => $row['event_context_json'] ? json_decode((string) $row['event_context_json'], true) : [],
        'created_at' => $row['created_at'] ?? null,
    ];
}

mg_require_method('GET');
$user = mg_require_permission('merchant.campaigns.view');
$merchantId = (int) $user['id'];
$pdo = mg_db();
mg_merchant_ensure_workspace($pdo, $user);
$campaignPublicId = strtolower(trim((string) ($_GET['campaign_id'] ?? '')));
$limit = min(200, max(1, (int) ($_GET['limit'] ?? 100)));

try {
    $sql = 'SELECT ce.*, c.public_id campaign_public_id, c.title campaign_title, wi.public_id wallet_item_public_id, cc.public_id contact_public_id, cc.email contact_email
            FROM campaign_events ce
            LEFT JOIN campaigns c ON c.id = ce.campaign_id
            LEFT JOIN wallet_items wi ON wi.id = ce.wallet_item_id
            LEFT JOIN campaign_contacts cc ON cc.id = ce.contact_id
            WHERE ce.merchant_user_id = ?';
    $params = [$merchantId];
    if ($campaignPublicId !== '') {
        $sql .= ' AND c.public_id = ?';
        $params[] = $campaignPublicId;
    }
    $sql .= ' ORDER BY ce.created_at DESC, ce.id DESC LIMIT ' . $limit;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $events = array_map('mg_campaign_event_row', $stmt->fetchAll());
    mg_ok(['events' => $events, 'count' => count($events), 'schema_ready' => true]);
} catch (Throwable $error) {
    mg_security_log('warning', 'merchant.campaign_events.unavailable', 'Campaign events unavailable.', ['exception_class' => $error::class], $merchantId);
    mg_ok(['events' => [], 'count' => 0, 'schema_ready' => false], 'Campaign events unavailable until the Stage 12 schema is installed.');
}
