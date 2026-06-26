<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/share-market/readiness-summary.php';

$user = mg_require_api_user();
mg_rate_limit('share_market.readiness_summary.status', 'user:' . (int)$user['id'], 30, 300);

try {
    mg_require_method('GET');
    mg_ok(['readiness_summary' => mg_share_market_readiness_summary(mg_db(), (int)$user['id'])], 'Readiness summary loaded.');
} catch (Throwable $e) {
    mg_fail('Unable to load readiness summary.', 500);
}
