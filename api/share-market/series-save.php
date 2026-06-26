<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/share-market/program-workflow.php';

mg_require_method('POST');
$input = mg_input();
mg_require_csrf_for_write($input);
$user = mg_require_api_user();
mg_rate_limit('share_market.series.save', 'user:' . (int)$user['id'], 20, 300);

try {
    $pdo = mg_db();
    $series = mg_share_market_validate_series_draft($input, $user, false);
    $series['created_at'] = gmdate('c');
    mg_share_market_program_append_event($pdo, 'share_market.program.series_draft_saved', $series, (int)$user['id']);
    mg_ok(['series' => $series, 'snapshot' => mg_share_market_user_snapshot($pdo, (int)$user['id'])], 'Series draft saved. No market was launched.');
} catch (InvalidArgumentException $e) {
    mg_fail($e->getMessage(), 422);
} catch (DomainException $e) {
    mg_fail($e->getMessage(), 403);
} catch (Throwable $e) {
    mg_security_log('error', 'share_market.series_save_failed', 'Unable to save Share Market series draft.', ['exception_class' => $e::class], (int)$user['id']);
    mg_fail('Unable to save Share Market series draft.', 500);
}
