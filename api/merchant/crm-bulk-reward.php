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
$templateRef = strtolower(trim((string)($input['reward_template_id'] ?? $input['template_id'] ?? '')));
$note = trim((string)($input['note'] ?? ''));
if ($templateRef === '' || strlen($templateRef) !== 36 || preg_match('/^[a-f0-9-]{36}$/', $templateRef) !== 1 || mb_strlen($note) > 1000) mg_fail('Invalid bulk reward request.', 422);
$batchKey = mg_crm_bulk_idempotency_key($input['idempotency_key'] ?? '', 'crm-bulk-reward', $merchantId, [$templateRef, $note, implode(',', $contactRefs)]);

try {
    mg_delivery_install_schema($pdo);
    $results = [];
    $segments = ['account_contacts' => 0, 'no_account_contacts' => 0];
    foreach ($contactRefs as $index => $contactRef) {
        try {
            $pdo->beginTransaction();
            $contacts = mg_crm_bulk_contacts($pdo, $merchantId, [$contactRef], true);
            if (!isset($contacts[$contactRef])) { $results[] = ['contact_id' => $contactRef, 'status' => 'failed', 'reason' => 'not_found']; $pdo->commit(); continue; }
            $contact = $contacts[$contactRef];
            $template = mg_crm_bulk_template($pdo, $merchantId, $templateRef);
            $contactKey = substr($batchKey . ':' . hash('sha256', $contactRef . ':' . $index), 0, 190);
            if ((int)($contact['user_id'] ?? 0) > 0) {
                $segments['account_contacts']++;
                $results[] = mg_crm_bulk_issue_direct_reward($pdo, $contact, $template, $merchantId, $note, 'direct:' . $contactKey);
            } else {
                $segments['no_account_contacts']++;
                $results[] = mg_crm_bulk_send_reward_invite($pdo, $contact, $template, $merchantId, $note, 'invite:' . $contactKey);
            }
            $pdo->commit();
        } catch (Throwable $contactError) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $results[] = ['contact_id' => $contactRef, 'status' => 'failed', 'reason' => 'reward_failed'];
        }
    }
    mg_ok(['summary' => mg_crm_bulk_result_summary($results), 'segments' => $segments, 'results' => $results], 'Bulk CRM reward processed.');
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('error', 'merchant.crm_bulk_reward.failed', 'Unable to process bulk CRM reward.', ['exception_class' => $error::class], $merchantId);
    mg_fail('Unable to process bulk CRM reward.', 500);
}
