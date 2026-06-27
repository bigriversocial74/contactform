<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';
require_once dirname(__DIR__, 2) . '/includes/merchant-crm-bulk.php';

mg_require_method('POST');
$user = mg_require_permission('merchant.campaigns.manage');
$merchantId = (int)$user['id'];
$pdo = mg_db();
mg_merchant_ensure_workspace($pdo, $user);
$input = mg_input();
mg_require_csrf_for_write($input);

$contactRefs = mg_crm_bulk_contact_ids($input['contact_ids'] ?? $input['contacts'] ?? []);
$note = trim((string)($input['note'] ?? ''));
$dueAt = trim((string)($input['due_at'] ?? ''));
if ($note === '' || mb_strlen($note) > 1000 || mb_strlen($dueAt) > 40) mg_fail('Follow-up note is required.', 422);
$batchKey = mg_crm_bulk_idempotency_key($input['idempotency_key'] ?? '', 'crm-followup', $merchantId, [$note, $dueAt, implode(',', $contactRefs)]);

try {
    $results = [];
    $pdo->beginTransaction();
    $contacts = mg_crm_bulk_contacts($pdo, $merchantId, $contactRefs, true);
    foreach ($contactRefs as $index => $contactRef) {
        if (!isset($contacts[$contactRef])) { $results[] = ['contact_id' => $contactRef, 'status' => 'failed', 'reason' => 'not_found']; continue; }
        $contact = $contacts[$contactRef];
        $contactKey = substr($batchKey . ':' . hash('sha256', $contactRef . ':' . $index), 0, 190);
        $duplicate = $pdo->prepare("SELECT public_id FROM campaign_events WHERE merchant_user_id=? AND contact_id=? AND event_type='crm.followup.created' AND JSON_UNQUOTE(JSON_EXTRACT(event_context_json,'$.idempotency_key'))=? LIMIT 1 FOR UPDATE");
        $duplicate->execute([$merchantId, (int)$contact['id'], $contactKey]);
        $existingId = (string)($duplicate->fetchColumn() ?: '');
        if ($existingId !== '') { $results[] = ['contact_id' => $contactRef, 'status' => 'duplicate', 'event_id' => $existingId, 'duplicate' => true]; continue; }
        $eventId = mg_crm_bulk_uuid();
        $context = ['note' => $note, 'due_at' => $dueAt !== '' ? $dueAt : null, 'idempotency_key' => $contactKey, 'bulk_action' => true];
        $pdo->prepare('INSERT INTO campaign_events (public_id,merchant_user_id,campaign_id,contact_id,event_type,event_context_json,created_at) VALUES (?,?,?,?,?,?,NOW())')->execute([$eventId, $merchantId, (int)$contact['campaign_id'], (int)$contact['id'], 'crm.followup.created', json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]);
        mg_merchant_crm_record_event($pdo, ['merchant_user_id' => $merchantId, 'campaign_id' => (int)$contact['campaign_id'], 'campaign_type' => (string)$contact['campaign_type'], 'event_type' => 'crm.followup.created', 'source_type' => 'merchant_crm_followup', 'source_public_id' => (string)$contact['public_id'], 'user_id' => (int)($contact['user_id'] ?? 0) > 0 ? (int)$contact['user_id'] : null, 'email' => (string)$contact['email'], 'name' => (string)($contact['name'] ?? ''), 'metadata' => $context]);
        $results[] = ['contact_id' => $contactRef, 'status' => 'sent', 'event_id' => $eventId, 'duplicate' => false];
    }
    $pdo->commit();
    mg_ok(['summary' => mg_crm_bulk_result_summary($results), 'results' => $results], 'Bulk CRM follow-up created.');
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('error', 'merchant.crm_followup.failed', 'Unable to create CRM follow-up.', ['exception_class' => $error::class], $merchantId);
    mg_fail('Unable to create CRM follow-up.', 500);
}
