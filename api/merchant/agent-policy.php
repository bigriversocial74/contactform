<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';
require_once dirname(__DIR__, 2) . '/includes/merchant-agent-policy.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$user = mg_require_api_user();
$pdo = mg_db();
$workspace = mg_merchant_ensure_workspace($pdo, $user);
$merchantUserId = (int)($workspace['merchant_user_id'] ?? $user['id']);

if ($method === 'POST') {
    $input = mg_input();
    mg_require_csrf_for_write($input);
    $action = strtolower(trim((string)($input['action'] ?? 'save_policy')));
    if ($action === 'save_policy') mg_ok(mg_agent_policy_save($pdo, $merchantUserId, (int)$user['id'], $input), 'Agent policy saved.');
    mg_ok(mg_agent_policy_memory_action($pdo, $merchantUserId, (int)$user['id'], $input), 'Agent memory control updated.');
}

mg_require_method('GET');
mg_ok(mg_agent_policy_response($pdo, $merchantUserId));
