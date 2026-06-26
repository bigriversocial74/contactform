<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__, 3) . '/includes/share-market/admin-readiness-dashboard.php';

$user = mg_require_api_user();
if (!mg_share_market_admin_authorized($user)) mg_fail('Share Market Admin permission is required.', 403);
mg_rate_limit('share_market.readiness_dashboard.admin', 'user:' . (int)$user['id'], 30, 300);

try {
    mg_require_method('GET');
    mg_ok(['readiness_dashboard' => mg_share_market_admin_readiness_dashboard(mg_db())], 'Readiness dashboard loaded.');
} catch (Throwable $e) {
    mg_fail('Unable to load readiness dashboard.', 500);
}
