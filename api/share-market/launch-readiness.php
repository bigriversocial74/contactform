<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/share-market/launch-readiness.php';

$user = mg_require_api_user();
mg_rate_limit('share_market.launch_readiness.status', 'user:' . (int)$user['id'], 30, 300);

try {
    mg_require_method('GET');
    mg_ok(['launch_readiness' => mg_share_market_launch_readiness_for_user(mg_db(), (int)$user['id'])], 'Launch readiness loaded.');
} catch (Throwable $e) {
    mg_fail('Unable to load launch readiness.', 500);
}
