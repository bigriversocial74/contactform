<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';
require_once dirname(__DIR__, 2) . '/includes/merchant-agent-approvals.php';

mg_require_method('POST');
$user = mg_require_permission('merchant.campaigns.manage');
$merchantId = (int)$user['id'];
$pdo = mg_db();
mg_merchant_ensure_workspace($pdo, $user);
$input = mg_input();
mg_require_csrf_for_write($input);
$approvalId = trim((string)($input['approval_id'] ?? ''));
$action = strtolower(trim((string)($input['action'] ?? '')));
if ($approvalId === '') mg_fail('Approval item is required.', 422);
if (!in_array($action, ['approve','reject','defer','create_task'], true)) mg_fail('Unknown approval action.', 422);
try {
    $item = mg_agent_approval_find_item($pdo, $merchantId, $approvalId);
    if (!$item) mg_fail('Approval item not found or no longer available.', 404);
    $pdo->beginTransaction();
    $result = mg_agent_approval_record_decision($pdo, $merchantId, (int)$user['id'], $item, $action, $input);
    $pdo->commit();
    mg_ok(['result' => $result, 'item' => array_diff_key($item, ['_rec' => true, '_event' => true])], 'Agent approval action recorded.');
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('error', 'merchant.agent_approval_action.failed', 'Unable to record agent approval action.', ['exception_class' => $error::class], $merchantId);
    mg_fail('Unable to record agent approval action.', 500);
}
