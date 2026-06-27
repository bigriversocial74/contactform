<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';
require_once dirname(__DIR__, 2) . '/includes/merchant-agent-messages.php';

mg_require_method('POST');
$user = mg_require_permission('merchant.campaigns.manage');
$merchantId = (int)$user['id'];
$pdo = mg_db();
mg_merchant_ensure_workspace($pdo, $user);
$input = mg_input();
mg_require_csrf_for_write($input);
$messageDraftId = trim((string)($input['message_draft_id'] ?? ''));
$action = strtolower(trim((string)($input['action'] ?? '')));
if ($messageDraftId === '') mg_fail('Message draft is required.', 422);
if (!in_array($action, ['edit_draft','approve_draft','send_message','discard_draft','convert_to_followup_task'], true)) mg_fail('Unknown message action.', 422);
try {
    $item = mg_agent_message_find_item($pdo, $merchantId, $messageDraftId);
    if (!$item) mg_fail('Message draft not found or no longer available.', 404);
    $pdo->beginTransaction();
    $result = mg_agent_message_perform($pdo, $merchantId, (int)$user['id'], $item, $action, $input);
    $pdo->commit();
    mg_ok(['result' => $result, 'item' => $item], 'Agent message action recorded.');
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('error', 'merchant.agent_message_action.failed', 'Unable to record agent message action.', ['exception_class' => $error::class], $merchantId);
    mg_fail('Unable to record agent message action.', 500);
}
