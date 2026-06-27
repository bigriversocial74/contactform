<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';
require_once dirname(__DIR__, 2) . '/includes/merchant-crm-action-history.php';

mg_require_method('GET');
$user = mg_require_permission('merchant.campaigns.view');
$merchantId = (int)$user['id'];
$pdo = mg_db();
mg_merchant_ensure_workspace($pdo, $user);

$typeFilter = strtolower(trim((string)($_GET['type'] ?? '')));
$statusFilter = strtolower(trim((string)($_GET['status'] ?? '')));
$campaignRef = strtolower(trim((string)($_GET['campaign'] ?? '')));
$limit = max(1, min(100, (int)($_GET['limit'] ?? 40)));

try {
    $where = ["ce.merchant_user_id=?", "ce.event_type='crm.bulk_action.result'"];
    $params = [$merchantId];
    if ($campaignRef !== '') { $where[] = '(c.public_id=? OR c.public_slug=?)'; $params[] = $campaignRef; $params[] = $campaignRef; }
    $sql = 'SELECT ce.public_id event_public_id,ce.event_context_json,ce.created_at,c.public_id campaign_public_id,c.public_slug,c.title campaign_title,c.campaign_type,cc.public_id contact_public_id,cc.name contact_name,cc.user_id contact_user_id FROM campaign_events ce LEFT JOIN campaigns c ON c.id=ce.campaign_id LEFT JOIN campaign_contacts cc ON cc.id=ce.contact_id WHERE ' . implode(' AND ', $where) . ' ORDER BY ce.created_at DESC,ce.id DESC LIMIT 1000';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $runs = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $ctx = mg_crm_action_history_json($row['event_context_json'] ?? null);
        $actionType = strtolower((string)($ctx['bulk_action_type'] ?? 'bulk'));
        $result = is_array($ctx['result'] ?? null) ? $ctx['result'] : [];
        $resultStatus = strtolower((string)($result['status'] ?? ($ctx['status'] ?? 'unknown')));
        if ($typeFilter !== '' && $actionType !== $typeFilter) continue;
        if ($statusFilter !== '' && $resultStatus !== $statusFilter) continue;
        $batchKey = (string)($ctx['bulk_batch_key'] ?? '');
        if ($batchKey === '') $batchKey = 'legacy:' . substr((string)$row['event_public_id'], 0, 13);
        $key = $batchKey . '|' . $actionType;
        if (!isset($runs[$key])) $runs[$key] = ['id' => hash('sha256', $key), 'batch_key' => $batchKey, 'action_type' => $actionType, 'title' => mg_crm_action_history_action_label($actionType), 'status' => 'complete', 'summary' => mg_crm_action_history_summary_seed(), 'campaigns' => [], 'recipients' => [], 'retry_contact_ids' => [], 'created_at' => (string)$row['created_at'], 'last_event_at' => (string)$row['created_at']];
        $run =& $runs[$key];
        $run['created_at'] = min((string)$run['created_at'], (string)$row['created_at']);
        $run['last_event_at'] = max((string)$run['last_event_at'], (string)$row['created_at']);
        $campaignId = (string)($row['campaign_public_id'] ?? '');
        if ($campaignId !== '' && !isset($run['campaigns'][$campaignId])) $run['campaigns'][$campaignId] = ['id' => $campaignId, 'slug' => $row['public_slug'] ?? null, 'title' => (string)($row['campaign_title'] ?? 'Campaign'), 'campaign_type' => (string)($row['campaign_type'] ?? '')];
        $run['summary']['selected']++;
        if (array_key_exists($resultStatus, $run['summary'])) $run['summary'][$resultStatus]++;
        if (!empty($result['duplicate']) || !empty($ctx['duplicate'])) $run['summary']['duplicates']++;
        $contactId = (string)($row['contact_public_id'] ?? ($ctx['contact_id'] ?? ($result['contact_id'] ?? '')));
        $recipient = ['contact_id' => $contactId, 'name' => (string)($row['contact_name'] ?? ($ctx['contact_name'] ?? '')), 'has_account' => (int)($row['contact_user_id'] ?? 0) > 0 || !empty($ctx['has_account']), 'status' => $resultStatus, 'reason' => (string)($result['reason'] ?? ($ctx['reason'] ?? '')), 'retryable' => !empty($ctx['retryable']) || mg_crm_action_history_retryable($result), 'created_at' => (string)$row['created_at']];
        if (count($run['recipients']) < 60) $run['recipients'][] = $recipient;
        if ($recipient['retryable'] && $contactId !== '') $run['retry_contact_ids'][$contactId] = $contactId;
        unset($run);
    }
    $actions = array_values(array_map(function (array $run): array { $run['campaigns'] = array_values($run['campaigns']); $run['retry_contact_ids'] = array_values($run['retry_contact_ids']); $run['status'] = mg_crm_action_history_run_status($run['summary']); return $run; }, $runs));
    usort($actions, fn(array $a, array $b): int => strcmp((string)$b['last_event_at'], (string)$a['last_event_at']));
    $actions = array_slice($actions, 0, $limit);
    $totals = mg_crm_action_history_summary_seed();
    foreach ($actions as $action) foreach ($totals as $key => $value) $totals[$key] += (int)($action['summary'][$key] ?? 0);
    mg_ok(['actions' => $actions, 'totals' => $totals, 'schema_ready' => true]);
} catch (Throwable $error) {
    mg_ok(['actions' => [], 'totals' => mg_crm_action_history_summary_seed(), 'schema_ready' => false], 'CRM action history unavailable until campaign events are installed.');
}
