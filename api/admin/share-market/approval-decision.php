<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__, 3) . '/includes/share-market/approval-sql-adapter.php';

mg_require_method('POST');
$input = mg_input();
mg_require_csrf_for_write($input);
$user = mg_require_api_user();
if (!mg_share_market_admin_authorized($user)) mg_fail('Share Market Admin permission is required.', 403);
mg_rate_limit('share_market.approval.decision', 'user:' . (int)$user['id'], 15, 300);

$requestId = trim((string)($input['request_id'] ?? ''));
$decision = strtolower(trim((string)($input['decision'] ?? '')));
$note = trim((string)($input['note'] ?? ''));
$password = (string)($input['password'] ?? '');
$confirmation = trim((string)($input['confirmation'] ?? ''));
$phrases = mg_share_market_approval_decision_phrases();

if ($requestId === '' || preg_match('/^[A-Za-z0-9-]{20,64}$/', $requestId) !== 1) mg_fail('Enter a valid approval request identifier.', 422);
if (!isset($phrases[$decision])) mg_fail('Select a valid approval decision.', 422);
if ($note === '' || strlen($note) > 1000) mg_fail('A decision note between 1 and 1,000 characters is required.', 422);
if (!hash_equals($phrases[$decision], $confirmation)) mg_fail('The typed confirmation phrase does not match.', 422);

$pdo = mg_db();

try {
    mg_share_market_approval_verify_password($pdo, (int)$user['id'], $password);
    $queue = mg_share_market_approval_sql_record_decision($pdo, $input, $user);
    $updated = null;
    foreach ($queue['items'] as $queueItem) {
        if ((string)$queueItem['request_id'] === $requestId) {
            $updated = $queueItem;
            break;
        }
    }
    mg_ok(['request' => $updated, 'summary' => $queue['summary']], 'Approval decision recorded. No share action was executed.');
} catch (DomainException $e) {
    mg_security_log('warning', 'share_market.approval_decision_denied', $e->getMessage(), ['request_id' => $requestId, 'decision' => $decision], (int)$user['id']);
    mg_fail($e->getMessage(), 403);
} catch (InvalidArgumentException $e) {
    mg_fail($e->getMessage(), 422);
} catch (Throwable $e) {
    mg_security_log('error', 'share_market.approval_decision_failed', 'Unable to record Share Market approval decision.', ['request_id' => $requestId, 'decision' => $decision, 'exception_class' => $e::class], (int)$user['id']);
    mg_fail('Unable to record the approval decision.', 500);
}
