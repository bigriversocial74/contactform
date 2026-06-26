<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__, 3) . '/includes/share-market/readiness-health-audit.php';

$user = mg_require_api_user();
if (!mg_share_market_admin_authorized($user)) mg_fail('Share Market Admin permission is required.', 403);
mg_rate_limit('share_market.readiness_health.admin', 'user:' . (int)$user['id'], 20, 300);

try {
    mg_require_method('GET');
    mg_ok(['readiness_health' => mg_share_market_readiness_health_audit(mg_db(), $user)], 'Readiness health audit loaded.');
} catch (Throwable $e) {
    mg_fail('Unable to load readiness health audit.', 500);
}
