<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/share-market/execution-lockbox.php';

$user = mg_require_api_user();
mg_rate_limit('share_market.execution_lockbox.status', 'user:' . (int)$user['id'], 30, 300);

try {
    mg_require_method('GET');
    mg_ok(['lockbox' => mg_share_market_lockbox_for_user(mg_db(), (int)$user['id'])], 'Execution readiness lockbox loaded.');
} catch (Throwable $e) {
    mg_fail('Unable to load execution readiness lockbox.', 500);
}
