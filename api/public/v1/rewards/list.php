<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/_public.php';

mg_require_method('GET');
$context = mg_public_context('distribution:rewards.status');
$pdo = $context['pdo'];
$linkedAccountId = trim((string)($_GET['linked_account_id'] ?? ''));
$externalUserId = trim((string)($_GET['external_user_id'] ?? ''));
$limit = max(1, min(100, (int)($_GET['limit'] ?? 50)));

if ($linkedAccountId === '') {
    mg_public_log($pdo, $context, 422, 'invalid_request', 'Missing linked account id.');
    mg_fail('Linked account ID is required.', 422);
}

if (str_starts_with($linkedAccountId, 'sandbox_linked_')) {
    $stmt = $pdo->prepare('SELECT * FROM public_api_sandbox_rewards WHERE merchant_user_id=? AND app_id=? AND linked_account_public_id=? ORDER BY created_at DESC,id DESC LIMIT ' . $limit);
    $stmt->execute([(int)$context['merchant_user_id'], (int)$context['app_id'], $linkedAccountId]);
    $rewards = array_map(static function(array $row): array {
        $itemId = 'sandbox_item_' . substr(hash('sha256', (string)$row['public_id'] . '|item'), 0, 24);
        return [
            'sandbox' => true,
            'reward_id' => (string)$row['public_id'],
            'program_id' => (string)$row['program_public_id'],
            'template_id' => (string)$row['template_public_id'],
            'external_event_id' => (string)$row['external_event_id'],
            'event_type' => (string)$row['event_type'],
            'status' => (string)$row['status'],
            'quantity' => (int)$row['quantity'],
            'item_id' => $itemId,
            'item_status' => (string)$row['status'],
            'title' => 'Sandbox Microgift reward',
            'issued_at' => (string)($row['created_at'] ?? ''),
            'updated_at' => (string)($row['updated_at'] ?? ''),
        ];
    }, $stmt->fetchAll());
    mg_public_log($pdo, $context, 200, 'sandbox_ok');
    mg_ok(['linked_account_id' => $linkedAccountId, 'sandbox' => true, 'rewards' => $rewards]);
}

$linkStmt = $pdo->prepare("SELECT * FROM developer_app_user_links WHERE public_id=? AND app_id=? AND merchant_user_id=? AND status='active' LIMIT 1");
$linkStmt->execute([$linkedAccountId, (int)$context['app_id'], (int)$context['merchant_user_id']]);
$link = $linkStmt->fetch();
if (!$link) {
    mg_public_log($pdo, $context, 404, 'linked_account_not_found');
    mg_fail('Linked Microgifter account not found.', 404);
}
if ($externalUserId !== '' && !hash_equals((string)$link['external_user_id'], $externalUserId)) {
    mg_public_log($pdo, $context, 403, 'linked_account_mismatch');
    mg_fail('Linked account does not match the external user.', 403);
}

$sourceId = $context['source_connection_id'];
$sql = "SELECT da.public_id AS reward_id,da.status,da.quantity,da.unit_value_cents,da.reserved_at,da.issued_at,da.created_at,da.updated_at,dp.public_id AS program_id,dp.name AS program_name,cpt.public_id AS template_id,cpv.title AS title,dr.public_id AS recipient_id,dse.public_id AS event_id,dse.external_event_id,dse.event_type,j.public_id AS job_id,j.status AS job_status,i.public_id AS item_id,i.status AS item_status,i.issued_at AS item_issued_at,i.viewed_at,i.claimed_at,i.redeemed_at,i.expires_at FROM distribution_allocations da INNER JOIN distribution_programs dp ON dp.id=da.program_id INNER JOIN distribution_recipients dr ON dr.id=da.recipient_id INNER JOIN distribution_program_products dpp ON dpp.id=da.program_product_id INNER JOIN catalog_pppm_templates cpt ON cpt.id=dpp.pppm_template_id INNER JOIN catalog_product_versions cpv ON cpv.id=cpt.product_version_id LEFT JOIN distribution_source_events dse ON dse.id=da.source_event_id LEFT JOIN distribution_issuance_jobs j ON j.allocation_id=da.id LEFT JOIN pppm_items i ON i.id=j.pppm_item_id WHERE dp.merchant_user_id=? AND dr.user_id=? AND (? IS NULL OR dse.connection_id IS NULL OR dse.connection_id=?) ORDER BY da.created_at DESC,da.id DESC,j.item_sequence ASC LIMIT " . $limit;
$stmt = $pdo->prepare($sql);
$stmt->execute([(int)$context['merchant_user_id'], (int)$link['microgifter_user_id'], $sourceId, $sourceId]);
$rows = $stmt->fetchAll();
$rewards = [];
foreach ($rows as $row) {
    $rewardId = (string)$row['reward_id'];
    if (!isset($rewards[$rewardId])) {
        $rewards[$rewardId] = [
            'reward_id' => $rewardId,
            'program_id' => (string)$row['program_id'],
            'program_name' => (string)$row['program_name'],
            'template_id' => (string)$row['template_id'],
            'external_event_id' => (string)($row['external_event_id'] ?? ''),
            'event_type' => (string)($row['event_type'] ?? ''),
            'status' => (string)$row['status'],
            'quantity' => (int)$row['quantity'],
            'unit_value_cents' => (int)$row['unit_value_cents'],
            'title' => (string)$row['title'],
            'issued_at' => (string)($row['issued_at'] ?? $row['created_at'] ?? ''),
            'updated_at' => (string)($row['updated_at'] ?? ''),
            'items' => [],
        ];
    }
    if (!empty($row['item_id']) || !empty($row['job_id'])) {
        $rewards[$rewardId]['items'][] = [
            'job_id' => $row['job_id'] ?? null,
            'job_status' => $row['job_status'] ?? null,
            'item_id' => $row['item_id'] ?? null,
            'item_status' => $row['item_status'] ?? null,
            'issued_at' => $row['item_issued_at'] ?? null,
            'viewed_at' => $row['viewed_at'] ?? null,
            'claimed_at' => $row['claimed_at'] ?? null,
            'redeemed_at' => $row['redeemed_at'] ?? null,
            'expires_at' => $row['expires_at'] ?? null,
        ];
    }
}

mg_public_log($pdo, $context, 200, 'ok');
mg_ok(['linked_account_id' => $linkedAccountId, 'external_user_id' => (string)$link['external_user_id'], 'rewards' => array_values($rewards)]);
