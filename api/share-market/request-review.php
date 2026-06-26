<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/share-market/sql-adapter.php';

mg_require_method('POST');
$input = mg_input();
mg_require_csrf_for_write($input);
$user = mg_require_api_user();
mg_rate_limit('share_market.program.request_review', 'user:' . (int)$user['id'], 8, 300);

try {
    $pdo = mg_db();
    $payload = mg_share_market_validate_enrollment_request($input, $user);
    mg_ok(mg_share_market_sql_submit_enrollment($pdo, $payload, (int)$user['id']), 'Share Market request submitted.', 201);
} catch (InvalidArgumentException $e) {
    mg_fail($e->getMessage(), 422);
} catch (DomainException $e) {
    mg_fail($e->getMessage(), 403);
} catch (Throwable $e) {
    mg_security_log('error', 'share_market.program_request_failed', 'Unable to submit Share Market request.', ['exception_class' => $e::class], (int)$user['id']);
    mg_fail('Unable to submit Share Market request.', 500);
}
