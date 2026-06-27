<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';
require_once dirname(__DIR__, 2) . '/includes/merchant-customer-agent-timeline.php';

mg_require_method('GET');
$user = mg_require_permission('merchant.campaigns.view');
$merchantId = (int)$user['id'];
$pdo = mg_db();
mg_merchant_ensure_workspace($pdo, $user);
try {
    mg_ok(mg_customer_agent_timeline($pdo, $merchantId, $_GET));
} catch (Throwable $error) {
    mg_security_log('error', 'merchant.customer_agent_timeline.failed', 'Unable to load customer agent timeline.', ['exception_class' => $error::class], $merchantId);
    mg_fail('Unable to load customer agent timeline.', 500);
}
