<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';
require_once dirname(__DIR__, 2) . '/includes/merchant-crm-scheduled-actions.php';

mg_require_method('POST');
$user = mg_require_permission('merchant.campaigns.manage');
$merchantId = (int)$user['id'];
$pdo = mg_db();
mg_merchant_ensure_workspace($pdo, $user);
$input = mg_input();
mg_require_csrf_for_write($input);
$limit = max(1, min(100, (int)($input['limit'] ?? 25)));

try {
    $result = mg_crm_scheduled_process_due($pdo, $merchantId, $limit);
    mg_ok($result, 'CRM scheduled actions processed.');
} catch (Throwable $error) {
    mg_security_log('error', 'merchant.crm_scheduled_actions.runner_failed', 'Unable to process CRM scheduled actions.', ['exception_class' => $error::class, 'message' => $error->getMessage()], $merchantId);
    mg_fail('Unable to process scheduled CRM actions.', 500);
}
