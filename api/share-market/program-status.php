<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/share-market/program-workflow.php';

mg_require_method('GET');
$user = mg_require_api_user();
mg_rate_limit('share_market.program.status', 'user:' . (int)$user['id'], 60, 300);

try {
    mg_ok(mg_share_market_user_snapshot(mg_db(), (int)$user['id']), 'Share Market program status loaded.');
} catch (Throwable $e) {
    mg_security_log('error', 'share_market.program_status_failed', 'Unable to load Share Market program status.', ['exception_class' => $e::class], (int)$user['id']);
    mg_fail('Unable to load Share Market program status.', 500);
}
