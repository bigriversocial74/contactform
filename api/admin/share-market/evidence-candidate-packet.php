<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__, 3) . '/includes/share-market/candidate-packet.php';

mg_require_method('GET');
$user = mg_require_api_user();
if (!mg_share_market_admin_authorized($user)) mg_fail('Share Market Admin permission is required.', 403);
mg_rate_limit('share_market.candidate_packet', 'user:' . (int)$user['id'], 80, 300);

$attemptId = trim((string)($_GET['attempt_id'] ?? ''));
$candidateId = trim((string)($_GET['candidate_id'] ?? ''));
if ($attemptId === '' || preg_match('/^[A-Za-z0-9-]{20,80}$/', $attemptId) !== 1) mg_fail('Enter a valid audit attempt identifier.', 422);
if ($candidateId === '' || preg_match('/^[A-Za-z0-9-]{20,80}$/', $candidateId) !== 1) mg_fail('Enter a valid candidate identifier.', 422);

try {
    mg_ok(['packet' => mg_share_market_candidate_packet(mg_db(), $attemptId, $candidateId, $user)], 'Candidate packet loaded.');
} catch (InvalidArgumentException $e) {
    mg_fail($e->getMessage(), 422);
} catch (Throwable $e) {
    mg_security_log('error', 'share_market.candidate_packet_failed', 'Unable to load candidate packet.', ['attempt_id' => $attemptId, 'candidate_id' => $candidateId, 'exception_class' => $e::class], (int)$user['id']);
    mg_fail('Unable to load candidate packet.', 500);
}
