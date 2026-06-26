<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__, 3) . '/includes/share-market/execution-lockbox.php';

$user = mg_require_api_user();
if (!mg_share_market_admin_authorized($user)) mg_fail('Share Market Admin permission is required.', 403);
mg_rate_limit('share_market.lockbox.admin', 'user:' . (int)$user['id'], 30, 300);

try {
    mg_require_method('GET');
    mg_ok(['lockbox' => mg_share_market_lockbox_admin_queue(mg_db())], 'Lockbox queue loaded.');
} catch (Throwable $e) {
    mg_fail('Unable to load lockbox queue.', 500);
}
