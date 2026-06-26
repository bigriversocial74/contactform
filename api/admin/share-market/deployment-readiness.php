<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__, 3) . '/includes/share-market/deployment-readiness.php';

mg_require_method('GET');
$user = mg_require_api_user();
if (!mg_share_market_admin_authorized($user)) mg_fail('Share Market Admin permission is required.', 403);
mg_rate_limit('share_market.deployment_readiness', 'user:' . (int)$user['id'], 80, 300);

try {
    mg_ok(['readiness' => mg_share_market_deployment_readiness(mg_db(), dirname(__DIR__, 3))], 'Deployment readiness loaded.');
} catch (Throwable $e) {
    mg_security_log('error', 'share_market.deployment_readiness_failed', 'Unable to load deployment readiness.', ['exception_class' => $e::class], (int)$user['id']);
    mg_fail('Unable to load deployment readiness.', 500);
}
