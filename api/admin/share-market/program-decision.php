<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__, 3) . '/includes/share-market/sql-adapter.php';

mg_require_method('POST');
$input = mg_input();
mg_require_csrf_for_write($input);
$user = mg_require_api_user();
if (!mg_share_market_admin_authorized($user)) mg_fail('Share Market Admin permission is required.', 403);
mg_rate_limit('share_market.program.decision', 'user:' . (int)$user['id'], 20, 300);

try {
    mg_ok(mg_share_market_sql_record_program_decision(mg_db(), $input, $user), 'Share Market review decision recorded. No market was launched.');
} catch (InvalidArgumentException $e) {
    mg_fail($e->getMessage(), 422);
} catch (DomainException $e) {
    mg_fail($e->getMessage(), 403);
} catch (Throwable $e) {
    mg_security_log('error', 'share_market.program_decision_failed', 'Unable to record Share Market review decision.', ['exception_class' => $e::class], (int)$user['id']);
    mg_fail('Unable to record Share Market review decision.', 500);
}
