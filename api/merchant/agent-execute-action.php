<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';
require_once dirname(__DIR__, 2) . '/includes/merchant-agent-execution.php';

mg_require_method('POST');
$user = mg_require_permission('merchant.campaigns.manage');
$merchantId = (int)$user['id'];
$pdo = mg_db();
mg_merchant_ensure_workspace($pdo, $user);
$input = mg_input();
mg_require_csrf_for_write($input);
$executionId = trim((string)($input['execution_id'] ?? ''));
$action = strtolower(trim((string)($input['action'] ?? '')));
if ($executionId === '') mg_fail('Execution item is required.', 422);
if (!in_array($action, ['execute_approved_action','create_followup_task','draft_customer_message','mark_skipped','retry_failed_execution'], true)) mg_fail('Unknown execution action.', 422);
try {
    $item = mg_agent_execution_find_item($pdo, $merchantId, $executionId);
    if (!$item) mg_fail('Execution item not found or no longer available.', 404);
    $pdo->beginTransaction();
    $result = mg_agent_execution_perform($pdo, $merchantId, (int)$user['id'], $item, $action, $input);
    $pdo->commit();
    mg_ok(['result' => $result, 'item' => $item], 'Agent execution action recorded.');
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('error', 'merchant.agent_execute_action.failed', 'Unable to record agent execution action.', ['exception_class' => $error::class], $merchantId);
    mg_fail('Unable to record agent execution action.', 500);
}
