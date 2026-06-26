<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__, 3) . '/includes/share-market/execution-prep.php';

mg_require_method('GET');
$user = mg_require_api_user();
if (!mg_share_market_admin_authorized($user)) mg_fail('Share Market Admin permission is required.', 403);
mg_rate_limit('share_market.execution.preview', 'user:' . (int)$user['id'], 60, 300);

$requestId = trim((string)($_GET['request_id'] ?? ''));
if ($requestId === '' || preg_match('/^[A-Za-z0-9-]{20,80}$/', $requestId) !== 1) mg_fail('Enter a valid approval request identifier.', 422);

try {
    mg_ok(['execution' => mg_share_market_execution_preview(mg_db(), $requestId, $user)], 'Execution preview loaded. No share action was executed.');
} catch (InvalidArgumentException $e) {
    mg_fail($e->getMessage(), 422);
} catch (Throwable $e) {
    mg_security_log('error', 'share_market.execution_preview_failed', 'Unable to load Share Market execution preview.', [
        'request_id' => $requestId,
        'exception_class' => $e::class,
    ], (int)$user['id']);
    mg_fail('Unable to load the execution preview.', 500);
}
