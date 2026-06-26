<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__, 3) . '/includes/share-market/candidate-comparison.php';

mg_require_method('GET');
$user = mg_require_api_user();
if (!mg_share_market_admin_authorized($user)) mg_fail('Share Market Admin permission is required.', 403);
mg_rate_limit('share_market.candidate_comparison', 'user:' . (int)$user['id'], 80, 300);

$attemptId = trim((string)($_GET['attempt_id'] ?? ''));
$leftId = trim((string)($_GET['left_candidate_id'] ?? ''));
$rightId = trim((string)($_GET['right_candidate_id'] ?? ''));
if ($attemptId === '' || preg_match('/^[A-Za-z0-9-]{20,80}$/', $attemptId) !== 1) mg_fail('Enter a valid audit attempt identifier.', 422);
if ($leftId === '' || preg_match('/^[A-Za-z0-9-]{20,80}$/', $leftId) !== 1) mg_fail('Enter a valid left candidate identifier.', 422);
if ($rightId === '' || preg_match('/^[A-Za-z0-9-]{20,80}$/', $rightId) !== 1) mg_fail('Enter a valid right candidate identifier.', 422);

try {
    mg_ok(['comparison' => mg_share_market_candidate_comparison(mg_db(), $attemptId, $leftId, $rightId, $user)], 'Candidate comparison loaded.');
} catch (InvalidArgumentException $e) {
    mg_fail($e->getMessage(), 422);
} catch (Throwable $e) {
    mg_security_log('error', 'share_market.candidate_comparison_failed', 'Unable to load candidate comparison.', ['attempt_id' => $attemptId, 'left_candidate_id' => $leftId, 'right_candidate_id' => $rightId, 'exception_class' => $e::class], (int)$user['id']);
    mg_fail('Unable to load candidate comparison.', 500);
}
