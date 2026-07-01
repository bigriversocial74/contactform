<?php
declare(strict_types=1);

require_once __DIR__ . '/_ai.php';
require_once dirname(__DIR__) . '/merchant/_merchant.php';
require_once dirname(__DIR__, 2) . '/includes/merchant-automation-controls.php';
require_once dirname(__DIR__, 2) . '/includes/ai/merchant-agent-chat.php';

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    mg_fail('Method not allowed.', 405);
}

$pdo = mg_db();
$user = mg_merchant_require_permission('merchant.ai.review');
mg_merchant_ensure_workspace($pdo, $user);
mg_agent_autonomy_require_for_merchant($pdo, (int)$user['id'], 'review_queue', 'agent review item creation');
$input = mg_input();
mg_require_csrf_for_write($input);
mg_ok(mg_ai_chat_bridge_to_review($pdo, $user, $input), 'Agent card added to review queue.', 201);
