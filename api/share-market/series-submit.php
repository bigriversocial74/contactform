<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/share-market/sql-adapter.php';
require_once dirname(__DIR__, 2) . '/includes/share-market/notifications.php';

mg_require_method('POST');
$input = mg_input();
mg_require_csrf_for_write($input);
$user = mg_require_api_user();
mg_rate_limit('share_market.series.submit', 'user:' . (int)$user['id'], 12, 300);

try {
    $pdo = mg_db();
    $series = mg_share_market_validate_series_draft($input, $user, true);
    $result = mg_share_market_sql_save_series($pdo, $series, (int)$user['id'], true);
    mg_share_market_notify_series_submitted($pdo, (int)$user['id'], $result);
    mg_ok($result, 'Series submitted for admin review. No market was launched.', 201);
} catch (InvalidArgumentException $e) {
    mg_fail($e->getMessage(), 422);
} catch (DomainException $e) {
    mg_fail($e->getMessage(), 403);
} catch (Throwable $e) {
    mg_security_log('error', 'share_market.series_submit_failed', 'Unable to submit Share Market series.', ['exception_class' => $e::class], (int)$user['id']);
    mg_fail('Unable to submit Share Market series.', 500);
}
