<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__, 3) . '/includes/share-market/credit-reserve.php';

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$user = mg_require_api_user();
if (!mg_share_market_admin_authorized($user)) mg_fail('Share Market Admin permission is required.', 403);
mg_rate_limit('share_market.credit_reserve.admin', 'user:' . (int)$user['id'], 30, 300);

try {
    if ($method === 'GET') {
        mg_ok(mg_share_market_credit_reserve_admin_queue(mg_db()), 'Credit reserve queue loaded.');
    }
    if ($method === 'POST') {
        $input = mg_input();
        mg_require_csrf_for_write($input);
        mg_ok(mg_share_market_credit_reserve_decide(mg_db(), $input, $user), 'Credit reserve decision recorded. No public market was launched.');
    }
    mg_fail('Method not allowed.', 405);
} catch (InvalidArgumentException $e) {
    mg_fail($e->getMessage(), 422);
} catch (DomainException $e) {
    mg_fail($e->getMessage(), 403);
} catch (Throwable $e) {
    mg_security_log('error', 'share_market.credit_reserve_admin_failed', 'Unable to process Share Market credit reserve request.', ['exception_class' => $e::class], (int)$user['id']);
    mg_fail('Unable to process Share Market credit reserve request.', 500);
}
