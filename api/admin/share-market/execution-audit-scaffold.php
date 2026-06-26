<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__, 3) . '/includes/share-market/execution-audit.php';

mg_require_method('POST');
$input = mg_input();
mg_require_csrf_for_write($input);
$user = mg_require_api_user();
if (!mg_share_market_admin_authorized($user)) mg_fail('Share Market Admin permission is required.', 403);
mg_rate_limit('share_market.execution.audit_scaffold', 'user:' . (int)$user['id'], 8, 300);

$requestId = trim((string)($input['request_id'] ?? ''));
$confirmation = trim((string)($input['confirmation'] ?? ''));
$password = (string)($input['password'] ?? '');
$runMode = strtolower(trim((string)($input['run_mode'] ?? 'dry_run')));
if ($requestId === '' || preg_match('/^[A-Za-z0-9-]{20,80}$/', $requestId) !== 1) mg_fail('Enter a valid approval request identifier.', 422);
if (!in_array($runMode, ['dry_run','live'], true)) mg_fail('Select dry_run or live mode.', 422);
if (!hash_equals('AUDIT SCAFFOLD', $confirmation)) mg_fail('Type AUDIT SCAFFOLD to create audit scaffolding only.', 422);

try {
    mg_share_market_approval_verify_password(mg_db(), (int)$user['id'], $password);
    mg_ok(['audit' => mg_share_market_execution_audit_create_bundle(mg_db(), $requestId, $user, $runMode)], 'Execution audit scaffolding recorded. No Share Market value action was executed.', 201);
} catch (DomainException $e) {
    mg_fail($e->getMessage(), 403);
} catch (InvalidArgumentException $e) {
    mg_fail($e->getMessage(), 422);
} catch (Throwable $e) {
    mg_security_log('error', 'share_market.execution_audit_scaffold_failed', 'Unable to create Share Market execution audit scaffolding.', [
        'request_id' => $requestId,
        'exception_class' => $e::class,
    ], (int)$user['id']);
    mg_fail('Unable to create execution audit scaffolding.', 500);
}
