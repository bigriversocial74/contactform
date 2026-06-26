<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__, 3) . '/includes/share-market/execution-audit-review.php';

mg_require_method('GET');
$user = mg_require_api_user();
if (!mg_share_market_admin_authorized($user)) mg_fail('Share Market Admin permission is required.', 403);
mg_rate_limit('share_market.audit_list', 'user:' . (int)$user['id'], 60, 300);

try {
    mg_ok(['audit' => mg_share_market_audit_review_list(mg_db(), [
        'status' => trim((string)($_GET['status'] ?? 'all')),
        'run_mode' => trim((string)($_GET['run_mode'] ?? 'all')),
        'target_type' => trim((string)($_GET['target_type'] ?? '')),
        'target_public_id' => trim((string)($_GET['target_public_id'] ?? '')),
        'request_public_id' => trim((string)($_GET['request_public_id'] ?? '')),
        'q' => trim((string)($_GET['q'] ?? '')),
        'limit' => (int)($_GET['limit'] ?? 25),
    ])], 'Audit records loaded.');
} catch (Throwable $e) {
    mg_security_log('error', 'share_market.audit_list_failed', 'Unable to load audit records.', ['exception_class' => $e::class], (int)$user['id']);
    mg_fail('Unable to load audit records.', 500);
}
