<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';
require_once dirname(__DIR__, 2) . '/includes/merchant-agent-approvals.php';

mg_require_method('GET');
$user = mg_require_permission('merchant.campaigns.view');
$merchantId = (int)$user['id'];
$pdo = mg_db();
mg_merchant_ensure_workspace($pdo, $user);
try {
    mg_ok(mg_agent_approval_queue($pdo, $merchantId, $_GET));
} catch (Throwable $error) {
    mg_security_log('error', 'merchant.agent_approvals.failed', 'Unable to load merchant agent approval queue.', ['exception_class' => $error::class], $merchantId);
    mg_fail('Unable to load agent approval queue.', 500);
}
