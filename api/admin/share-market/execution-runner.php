<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__, 3) . '/includes/share-market/execution-prep.php';

mg_require_method('POST');
$input = mg_input();
mg_require_csrf_for_write($input);
$user = mg_require_api_user();
if (!mg_share_market_admin_authorized($user)) mg_fail('Share Market Admin permission is required.', 403);
mg_rate_limit('share_market.execution.runner', 'user:' . (int)$user['id'], 10, 300);

$requestId = trim((string)($input['request_id'] ?? ''));
$confirmation = trim((string)($input['confirmation'] ?? ''));
$password = (string)($input['password'] ?? '');
if ($requestId === '' || preg_match('/^[A-Za-z0-9-]{20,80}$/', $requestId) !== 1) mg_fail('Enter a valid approval request identifier.', 422);
if (!hash_equals('EXECUTION LOCKED', $confirmation)) mg_fail('Type EXECUTION LOCKED to confirm this is a locked runner stub.', 422);

try {
    mg_share_market_approval_verify_password(mg_db(), (int)$user['id'], $password);
    mg_ok(['execution' => mg_share_market_execution_runner_stub(mg_db(), $requestId, $user)], 'Execution runner is locked. No share action was executed.');
} catch (DomainException $e) {
    mg_fail($e->getMessage(), 403);
} catch (InvalidArgumentException $e) {
    mg_fail($e->getMessage(), 422);
} catch (Throwable $e) {
    mg_security_log('error', 'share_market.execution_runner_failed', 'Unable to load Share Market execution runner stub.', [
        'request_id' => $requestId,
        'exception_class' => $e::class,
    ], (int)$user['id']);
    mg_fail('Unable to load the locked execution runner.', 500);
}
