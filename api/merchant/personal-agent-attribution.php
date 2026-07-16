<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';
require_once dirname(__DIR__,2) . '/includes/personal-gifting-agent.php';

mg_require_method('GET');
$user = mg_require_permission('merchant.campaigns.view');
$pdo = mg_db();
mg_merchant_ensure_workspace($pdo,$user);

try {
    $days = max(1,min(365,(int)($_GET['days'] ?? 90)));
    $attribution = mg_personal_agent_opportunity_merchant_analytics($pdo,(int)$user['id'],$days);
    $attribution['recovery'] = mg_personal_agent_recovery_schema_ready($pdo)
        ? mg_personal_agent_recovery_merchant_analytics($pdo,(int)$user['id'],$days)
        : ['summary'=>[],'by_trigger'=>[],'days'=>$days,'available'=>false];
    mg_ok($attribution);
} catch (RuntimeException $error) {
    mg_fail($error->getMessage(),503);
} catch (Throwable $error) {
    mg_security_log('error','merchant.personal_agent_attribution.failed','Unable to load Personal Agent opportunity attribution.',['exception_class'=>$error::class],(int)$user['id']);
    mg_fail('Unable to load Personal Agent opportunity attribution.',500);
}
