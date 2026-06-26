<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/share-market/sql-adapter.php';

mg_require_method('POST');
$input = mg_input();
mg_require_csrf_for_write($input);
$user = mg_require_api_user();
mg_rate_limit('share_market.series.save', 'user:' . (int)$user['id'], 20, 300);

try {
    $pdo = mg_db();
    $series = mg_share_market_validate_series_draft($input, $user, false);
    mg_ok(mg_share_market_sql_save_series($pdo, $series, (int)$user['id'], false), 'Series draft saved. No market was launched.');
} catch (InvalidArgumentException $e) {
    mg_fail($e->getMessage(), 422);
} catch (DomainException $e) {
    mg_fail($e->getMessage(), 403);
} catch (Throwable $e) {
    mg_security_log('error', 'share_market.series_save_failed', 'Unable to save Share Market series draft.', ['exception_class' => $e::class], (int)$user['id']);
    mg_fail('Unable to save Share Market series draft.', 500);
}
