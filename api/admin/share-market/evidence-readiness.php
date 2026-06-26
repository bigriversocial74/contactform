<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__, 3) . '/includes/share-market/evidence-readiness.php';

mg_require_method('GET');
$user = mg_require_api_user();
if (!mg_share_market_admin_authorized($user)) mg_fail('Share Market Admin permission is required.', 403);
mg_rate_limit('share_market.evidence_readiness', 'user:' . (int)$user['id'], 80, 300);

$attemptId = trim((string)($_GET['attempt_id'] ?? ''));
if ($attemptId === '' || preg_match('/^[A-Za-z0-9-]{20,80}$/', $attemptId) !== 1) mg_fail('Enter a valid audit attempt identifier.', 422);

try {
    mg_ok(['readiness' => mg_share_market_evidence_package(mg_db(), $attemptId)], 'Evidence readiness loaded.');
} catch (InvalidArgumentException $e) {
    mg_fail($e->getMessage(), 422);
} catch (Throwable $e) {
    mg_security_log('error', 'share_market.evidence_readiness_failed', 'Unable to load evidence readiness.', ['attempt_id' => $attemptId, 'exception_class' => $e::class], (int)$user['id']);
    mg_fail('Unable to load evidence readiness.', 500);
}
