<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__, 3) . '/includes/share-market/ledger-simulator.php';

mg_require_method('GET');
$user = mg_require_api_user();
if (!mg_share_market_admin_authorized($user)) mg_fail('Share Market Admin permission is required.', 403);
mg_rate_limit('share_market.ledger.simulator', 'user:' . (int)$user['id'], 40, 300);

$requestId = trim((string)($_GET['request_id'] ?? ''));
$runMode = strtolower(trim((string)($_GET['run_mode'] ?? 'dry_run')));
if ($requestId === '' || preg_match('/^[A-Za-z0-9-]{20,80}$/', $requestId) !== 1) mg_fail('Enter a valid approval request identifier.', 422);
if (!in_array($runMode, ['dry_run','live'], true)) mg_fail('Select dry_run or live mode.', 422);

try {
    mg_ok(['simulation' => mg_share_market_ledger_simulation(mg_db(), $requestId, $user, $runMode)], 'Ledger simulator completed. No share action was executed.');
} catch (InvalidArgumentException $e) {
    mg_fail($e->getMessage(), 422);
} catch (Throwable $e) {
    mg_security_log('error', 'share_market.ledger_simulator_failed', 'Unable to run Share Market ledger simulator.', [
        'request_id' => $requestId,
        'exception_class' => $e::class,
    ], (int)$user['id']);
    mg_fail('Unable to run the ledger simulator.', 500);
}
