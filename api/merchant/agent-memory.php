<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';
require_once dirname(__DIR__, 2) . '/includes/merchant-agent-memory.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$user = mg_require_api_user();
$pdo = mg_db();
$workspace = mg_merchant_ensure_workspace($pdo, $user);
$merchantUserId = (int)($workspace['merchant_user_id'] ?? $user['id']);

if ($method === 'POST') {
    $input = mg_input();
    mg_require_csrf_for_write($input);
    mg_ok(mg_agent_memory_record($pdo, $merchantUserId, (int)$user['id'], $input), 'Agent feedback saved.');
}

mg_require_method('GET');
mg_ok(['memory' => mg_agent_memory_summary($pdo, $merchantUserId)]);
