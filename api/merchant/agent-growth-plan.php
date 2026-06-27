<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';
require_once dirname(__DIR__, 2) . '/includes/merchant-agent-growth-plan.php';

mg_require_method('GET');
$user = mg_require_permission('merchant.campaigns.view');
$merchantId = (int)$user['id'];
$pdo = mg_db();
mg_merchant_ensure_workspace($pdo, $user);
try {
    mg_ok(mg_agent_growth_plan($pdo, $merchantId, $_GET));
} catch (Throwable $error) {
    mg_security_log('error', 'merchant.agent_growth_plan.failed', 'Unable to load merchant agent growth plan.', ['exception_class' => $error::class], $merchantId);
    mg_fail('Unable to load agent growth plan.', 500);
}
