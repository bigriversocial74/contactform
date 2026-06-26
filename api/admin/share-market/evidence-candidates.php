<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__, 3) . '/includes/share-market/evidence-candidates.php';

$user = mg_require_api_user();
if (!mg_share_market_admin_authorized($user)) mg_fail('Share Market Admin permission is required.', 403);
mg_rate_limit('share_market.evidence_candidates', 'user:' . (int)$user['id'], 60, 300);

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$input = $method === 'GET' ? $_GET : mg_input();
$attemptId = trim((string)($input['attempt_id'] ?? ''));
if ($attemptId === '' || preg_match('/^[A-Za-z0-9-]{20,80}$/', $attemptId) !== 1) mg_fail('Enter a valid audit attempt identifier.', 422);

try {
    if ($method === 'GET') {
        mg_ok(['candidates' => mg_share_market_candidate_list(mg_db(), $attemptId, $user)], 'Evidence candidates loaded.');
    }
    if ($method === 'POST') {
        mg_require_csrf_for_write($input);
        $action = trim((string)($input['action'] ?? 'record'));
        if ($action === 'record') {
            mg_ok(['candidate' => mg_share_market_candidate_record(mg_db(), $attemptId, $user, $input)], 'Evidence candidate recorded. No Buy-In value state was changed.', 201);
        }
        if ($action === 'revoke') {
            $candidateId = trim((string)($input['candidate_id'] ?? ''));
            mg_ok(['candidate' => mg_share_market_candidate_revoke(mg_db(), $attemptId, $candidateId, $user, $input)], 'Evidence candidate revoked. No Buy-In value state was changed.');
        }
        mg_fail('Unsupported candidate action.', 422);
    }
    mg_fail('Unsupported method.', 405);
} catch (InvalidArgumentException $e) {
    mg_fail($e->getMessage(), 422);
} catch (Throwable $e) {
    mg_security_log('error', 'share_market.evidence_candidates_failed', 'Unable to process evidence candidate request.', ['attempt_id' => $attemptId, 'exception_class' => $e::class], (int)$user['id']);
    mg_fail('Unable to process evidence candidate request.', 500);
}
