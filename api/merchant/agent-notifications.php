<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';
require_once dirname(__DIR__, 2) . '/includes/merchant-agent-notification-digest.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$user = mg_require_api_user();
$pdo = mg_db();
$workspace = mg_merchant_ensure_workspace($pdo, $user);
$merchantUserId = (int)($workspace['merchant_user_id'] ?? $user['id']);

if ($method === 'POST') {
    $input = mg_input();
    mg_require_csrf_for_write($input);
    mg_agent_digest_record_decision(
        $pdo,
        $merchantUserId,
        (int)$user['id'],
        trim((string)($input['id'] ?? '')),
        trim((string)($input['action'] ?? 'mark_read'))
    );
    mg_ok(mg_agent_digest_response($pdo, $merchantUserId, (string)($input['filter'] ?? 'all'), 50), 'Agent notification updated.');
}

mg_require_method('GET');
$filter = strtolower(trim((string)($_GET['filter'] ?? 'all')));
$limit = max(1, min(100, (int)($_GET['limit'] ?? 50)));
mg_ok(mg_agent_digest_response($pdo, $merchantUserId, $filter, $limit));
