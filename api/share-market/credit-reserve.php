<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/share-market/credit-reserve.php';

mg_require_method('POST');
$input = mg_input();
mg_require_csrf_for_write($input);
$user = mg_require_api_user();
mg_rate_limit('share_market.credit_reserve.submit', 'user:' . (int)$user['id'], 8, 300);

try {
    $data = mg_share_market_credit_reserve_submit(mg_db(), $input, $user);
    mg_ok($data, 'Share credit reserve request submitted for admin review.', 201);
} catch (InvalidArgumentException $e) {
    mg_fail($e->getMessage(), 422);
} catch (DomainException $e) {
    mg_fail($e->getMessage(), 403);
} catch (Throwable $e) {
    mg_security_log('error', 'share_market.credit_reserve_failed', 'Unable to submit Share Market credit reserve request.', ['exception_class' => $e::class], (int)$user['id']);
    mg_fail('Unable to submit Share Market credit reserve request.', 500);
}
