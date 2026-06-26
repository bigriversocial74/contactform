<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/share-market/program-workflow.php';

mg_require_method('POST');
$input = mg_input();
mg_require_csrf_for_write($input);
$user = mg_require_api_user();
mg_rate_limit('share_market.series.submit', 'user:' . (int)$user['id'], 12, 300);

try {
    $pdo = mg_db();
    $snapshot = mg_share_market_user_snapshot($pdo, (int)$user['id']);
    $enrollmentState = (string)($snapshot['enrollment']['status'] ?? 'not_enrolled');
    if (!in_array($enrollmentState, ['approved','active','under_review'], true)) {
        mg_fail('Submit a Share Market participation request before submitting a series.', 409);
    }
    $series = mg_share_market_validate_series_draft($input, $user, true);
    $series['submitted_at'] = gmdate('c');
    mg_share_market_program_append_event($pdo, 'share_market.program.series_submitted', $series, (int)$user['id']);
    mg_ok(['series' => $series, 'snapshot' => mg_share_market_user_snapshot($pdo, (int)$user['id'])], 'Series submitted for admin review. No market was launched.', 201);
} catch (InvalidArgumentException $e) {
    mg_fail($e->getMessage(), 422);
} catch (DomainException $e) {
    mg_fail($e->getMessage(), 403);
} catch (Throwable $e) {
    mg_security_log('error', 'share_market.series_submit_failed', 'Unable to submit Share Market series.', ['exception_class' => $e::class], (int)$user['id']);
    mg_fail('Unable to submit Share Market series.', 500);
}
