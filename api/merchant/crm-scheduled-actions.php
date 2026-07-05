<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';
require_once dirname(__DIR__, 2) . '/includes/merchant-crm-scheduled-actions.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$user = $method === 'GET' ? mg_require_permission('merchant.campaigns.view') : mg_require_permission('merchant.campaigns.manage');
$merchantId = (int)$user['id'];
$pdo = mg_db();
mg_merchant_ensure_workspace($pdo, $user);

if (!mg_crm_scheduled_ready($pdo)) {
    mg_fail('CRM scheduled action schema is not installed.', 503);
}

if ($method === 'GET') {
    $limit = max(1, min(100, (int)($_GET['limit'] ?? 50)));
    $stmt = $pdo->prepare("SELECT b.public_id,b.action_type,b.status,b.selected_count,b.scheduled_count,b.processed_count,b.skipped_count,b.failed_count,b.scheduled_at,b.payload_json,b.result_summary_json,b.created_at,b.updated_at FROM crm_scheduled_action_batches b WHERE b.merchant_user_id=? ORDER BY b.created_at DESC LIMIT {$limit}");
    $stmt->execute([$merchantId]);
    $batches = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $batches[] = [
            'id' => (string)$row['public_id'],
            'action_type' => (string)$row['action_type'],
            'status' => (string)$row['status'],
            'selected_count' => (int)$row['selected_count'],
            'scheduled_count' => (int)$row['scheduled_count'],
            'processed_count' => (int)$row['processed_count'],
            'skipped_count' => (int)$row['skipped_count'],
            'failed_count' => (int)$row['failed_count'],
            'scheduled_at' => $row['scheduled_at'] ?? null,
            'payload' => mg_crm_scheduled_json($row['payload_json'] ?? ''),
            'summary' => mg_crm_scheduled_json($row['result_summary_json'] ?? ''),
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }
    mg_ok(['batches' => $batches, 'count' => count($batches)]);
}

if ($method !== 'POST') mg_fail('Method not allowed.', 405);
$input = mg_input();
mg_require_csrf_for_write($input);

$actionType = strtolower(trim((string)($input['action_type'] ?? '')));
if (!in_array($actionType, ['campaign_reward', 'reward_template', 'message'], true)) mg_fail('Invalid scheduled action type.', 422);
$contactRefs = mg_crm_bulk_contact_ids($input['contact_ids'] ?? $input['contacts'] ?? $input['contact_id'] ?? []);
$scheduledAt = mg_crm_scheduled_time($input['scheduled_at'] ?? $input['send_at'] ?? '');
$note = trim((string)($input['note'] ?? ''));
$message = trim((string)($input['message'] ?? ''));
$sendMessage = !empty($input['send_message']);
$allowDuplicate = !empty($input['allow_duplicate']);
if (mb_strlen($note) > 1000 || mb_strlen($message) > 4000) mg_fail('Scheduled action content is too long.', 422);

$campaignRef = strtolower(trim((string)($input['campaign_id'] ?? $input['campaign'] ?? '')));
$templateRef = strtolower(trim((string)($input['reward_template_id'] ?? $input['template_id'] ?? '')));
$batchKey = mg_crm_scheduled_idempotency($input['idempotency_key'] ?? '', 'crm-scheduled-' . $actionType, $merchantId, [$actionType, implode(',', $contactRefs), $campaignRef, $templateRef, $scheduledAt, $note, $message]);

try {
    $pdo->beginTransaction();
    $contacts = mg_crm_bulk_contacts($pdo, $merchantId, $contactRefs, true);
    $campaignDbId = null;
    $templateDbId = null;
    $campaignPayload = null;
    $templatePayloadId = null;

    if ($actionType === 'campaign_reward') {
        if ($campaignRef === '' || strlen($campaignRef) !== 36 || preg_match('/^[a-f0-9-]{36}$/', $campaignRef) !== 1) mg_fail('Choose a campaign to schedule.', 422);
        $campaign = mg_crm_scheduled_load_campaign($pdo, $merchantId, $campaignRef, true);
        $campaignDbId = (int)$campaign['id'];
        $templateDbId = (int)$campaign['reward_template_db_id'];
        $campaignPayload = ['campaign_id' => (string)$campaign['public_id'], 'campaign_title' => (string)$campaign['title'], 'reward_template_id' => (string)$campaign['reward_template_public_id']];
        $templatePayloadId = (string)$campaign['reward_template_public_id'];
    } elseif ($actionType === 'reward_template') {
        if ($templateRef === '' || strlen($templateRef) !== 36 || preg_match('/^[a-f0-9-]{36}$/', $templateRef) !== 1) mg_fail('Choose a reward template to schedule.', 422);
        $template = mg_crm_bulk_template($pdo, $merchantId, $templateRef);
        $templateDbId = (int)$template['id'];
        $templatePayloadId = (string)$template['public_id'];
    } elseif ($actionType === 'message' && $message === '') {
        mg_fail('Message is required before scheduling.', 422);
    }

    $existing = $pdo->prepare('SELECT public_id FROM crm_scheduled_action_batches WHERE merchant_user_id=? AND idempotency_key=? LIMIT 1');
    $existing->execute([$merchantId, $batchKey]);
    $oldBatch = (string)($existing->fetchColumn() ?: '');
    if ($oldBatch !== '') {
        $pdo->commit();
        mg_ok(['batch_id' => $oldBatch, 'duplicate' => true], 'Scheduled CRM batch already exists.');
    }

    $batchId = mg_crm_scheduled_uuid();
    $selectedCount = count($contactRefs);
    $payloadBase = [
        'action_type' => $actionType,
        'note' => $note,
        'message' => $message,
        'send_message' => $sendMessage,
        'allow_duplicate' => $allowDuplicate,
        'scheduled_at' => $scheduledAt,
    ] + ($campaignPayload ?: []);
    if ($actionType === 'reward_template') $payloadBase['reward_template_id'] = $templatePayloadId;

    $pdo->prepare('INSERT INTO crm_scheduled_action_batches (public_id,merchant_user_id,action_type,status,selected_count,scheduled_count,scheduled_at,idempotency_key,payload_json,result_summary_json,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,NOW(),NOW())')
        ->execute([$batchId, $merchantId, $actionType, 'scheduled', $selectedCount, $selectedCount, $scheduledAt, $batchKey, json_encode($payloadBase, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), json_encode(['selected' => $selectedCount, 'scheduled' => $selectedCount], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]);
    $batchDbId = (int)$pdo->lastInsertId();

    $results = [];
    foreach ($contactRefs as $i => $contactRef) {
        if (!isset($contacts[$contactRef])) {
            $results[] = ['contact_id' => $contactRef, 'status' => 'failed', 'reason' => 'not_found'];
            continue;
        }
        $contact = $contacts[$contactRef];
        $payload = $payloadBase + ['contact_id' => $contactRef, 'contact_email' => (string)($contact['email'] ?? ''), 'contact_name' => (string)($contact['name'] ?? '')];
        $actionId = mg_crm_scheduled_uuid();
        $itemKey = substr($batchKey . ':' . hash('sha256', $contactRef . ':' . $i), 0, 190);
        $pdo->prepare('INSERT INTO crm_scheduled_actions (public_id,batch_id,merchant_user_id,campaign_id,contact_id,reward_template_id,action_type,status,scheduled_at,idempotency_key,payload_json,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())')
            ->execute([$actionId, $batchDbId, $merchantId, $campaignDbId, (int)$contact['id'], $templateDbId, $actionType, 'scheduled', $scheduledAt, $itemKey, json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]);
        $results[] = ['contact_id' => $contactRef, 'action_id' => $actionId, 'status' => 'scheduled'];
    }

    $summary = mg_crm_scheduled_result_summary($results);
    $pdo->prepare('UPDATE crm_scheduled_action_batches SET scheduled_count=?, result_summary_json=?, updated_at=NOW() WHERE id=?')
        ->execute([(int)$summary['scheduled'], json_encode($summary, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $batchDbId]);
    $pdo->commit();
    mg_ok(['batch_id' => $batchId, 'summary' => $summary, 'results' => $results, 'scheduled_at' => $scheduledAt], 'CRM action scheduled.', 201);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('error', 'merchant.crm_scheduled_actions.create_failed', 'Unable to create CRM scheduled action.', ['exception_class' => $error::class, 'message' => $error->getMessage()], $merchantId);
    mg_fail('Unable to schedule CRM action.', 500);
}
