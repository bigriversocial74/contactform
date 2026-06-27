<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';
require_once dirname(__DIR__) . '/gifts/_gift.php';
require_once dirname(__DIR__, 2) . '/includes/merchant-crm-bulk.php';
require_once dirname(__DIR__, 2) . '/includes/merchant-crm-action-history.php';

mg_require_method('POST');
$user = mg_require_permission('merchant.campaigns.manage');
$merchantId = (int)$user['id'];
$pdo = mg_db();
mg_merchant_ensure_workspace($pdo, $user);
$input = mg_input();
mg_require_csrf_for_write($input);

$contactRefs = mg_crm_bulk_contact_ids($input['contact_ids'] ?? $input['contacts'] ?? []);
$body = mg_message_validate_body($input['message'] ?? $input['body'] ?? '');
$batchKey = mg_crm_bulk_idempotency_key($input['idempotency_key'] ?? '', 'crm-bulk-message', $merchantId, [$body, implode(',', $contactRefs)]);

try {
    mg_delivery_install_schema($pdo);
    $results = [];
    $pdo->beginTransaction();
    $contacts = mg_crm_bulk_contacts($pdo, $merchantId, $contactRefs, true);
    foreach ($contactRefs as $index => $contactRef) {
        if (!isset($contacts[$contactRef])) {
            $results[] = ['contact_id' => $contactRef, 'status' => 'failed', 'reason' => 'not_found'];
            continue;
        }
        $contact = $contacts[$contactRef];
        try {
            $contactKey = substr($batchKey . ':' . hash('sha256', $contactRef . ':' . $index), 0, 190);
            $result = mg_crm_bulk_queue_message($pdo, $contact, $merchantId, $body, $contactKey);
            mg_crm_action_history_record_result($pdo, $contact, $merchantId, $batchKey, 'message', $result, ['message_length' => mb_strlen($body)]);
            $results[] = $result;
        } catch (Throwable $contactError) {
            $result = ['contact_id' => $contactRef, 'status' => 'failed', 'reason' => 'message_failed'];
            mg_crm_action_history_record_result($pdo, $contact, $merchantId, $batchKey, 'message', $result, ['message_length' => mb_strlen($body)]);
            $results[] = $result;
        }
    }
    $pdo->commit();
    mg_ok(['summary' => mg_crm_bulk_result_summary($results), 'results' => $results], 'Bulk CRM message processed.');
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('error', 'merchant.crm_bulk_message.failed', 'Unable to process bulk CRM message.', ['exception_class' => $error::class], $merchantId);
    mg_fail('Unable to process bulk CRM message.', 500);
}
