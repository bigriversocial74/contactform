<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__, 3) . '/includes/share-market/approval-workflow.php';

mg_require_method('GET');
$user = mg_require_api_user();
if (!mg_share_market_admin_authorized($user)) mg_fail('Share Market Admin permission is required.', 403);
mg_rate_limit('share_market.approval.queue', 'user:' . (int)$user['id'], 60, 300);

try {
    mg_ok(mg_share_market_approval_queue(mg_db(), $user), 'Share Market approval queue loaded.');
} catch (Throwable $e) {
    mg_security_log('error', 'share_market.approval_queue_failed', 'Unable to load Share Market approval queue.', [
        'exception_class' => $e::class,
    ], (int)$user['id']);
    mg_fail('Unable to load the approval queue.', 500);
}
