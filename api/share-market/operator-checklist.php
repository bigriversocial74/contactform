<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/share-market/operator-checklist.php';

$user = mg_require_api_user();
mg_rate_limit('share_market.operator_checklist.status', 'user:' . (int)$user['id'], 30, 300);

try {
    mg_require_method('GET');
    mg_ok(['operator_checklist' => mg_share_market_operator_checklist_status(mg_db(), (int)$user['id'])], 'Operator checklist status loaded.');
} catch (Throwable $e) {
    mg_fail('Unable to load operator checklist status.', 500);
}
