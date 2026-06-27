<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';
require_once dirname(__DIR__, 2) . '/includes/merchant-agent-action-composer.php';

mg_require_method('POST');
$user = mg_require_permission('merchant.campaigns.manage');
$merchantId = (int)$user['id'];
$pdo = mg_db();
mg_merchant_ensure_workspace($pdo, $user);
$input = mg_input();
mg_require_csrf_for_write($input);
$composerDraftId = trim((string)($input['composer_draft_id'] ?? ''));
$action = strtolower(trim((string)($input['action'] ?? '')));
if ($composerDraftId === '') mg_fail('Composer draft is required.', 422);
if (!in_array($action, ['create_draft','submit_for_review','seed_message','seed_followup'], true)) mg_fail('Unknown composer action.', 422);
try {
    $item = mg_agent_composer_find_item($pdo, $merchantId, $composerDraftId);
    if (!$item) mg_fail('Composer item not found.', 404);
    $pdo->beginTransaction();
    $result = mg_agent_composer_perform($pdo, $merchantId, (int)$user['id'], $item, $action, $input);
    $pdo->commit();
    mg_ok(['result' => $result, 'item' => $item], 'Composer event saved.');
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('error', 'merchant.agent_composer_write.failed', 'Unable to save composer event.', ['exception_class' => $error::class], $merchantId);
    mg_fail('Unable to save composer event.', 500);
}
