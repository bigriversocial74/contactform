<?php
declare(strict_types=1);

require_once __DIR__ . '/_ai.php';
require_once dirname(__DIR__) . '/merchant/_merchant.php';
require_once dirname(__DIR__, 2) . '/includes/merchant-automation-controls.php';
require_once dirname(__DIR__, 2) . '/includes/ai/merchant-agent-chat-memory.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$pdo = mg_db();

if ($method === 'GET') {
    $user = mg_merchant_require_permission('merchant.ai.review');
    mg_merchant_ensure_workspace($pdo, $user);
    $state = mg_ai_chat_public_state($pdo, (int)$user['id']);
    $state['memory'] = mg_agent_memory_summary($pdo, (int)$user['id']);
    $state['agent_autonomy'] = mg_agent_autonomy_for_merchant($pdo, (int)$user['id']);
    mg_ok($state);
}

if ($method === 'POST') {
    $user = mg_merchant_require_permission('merchant.ai.plan');
    mg_merchant_ensure_workspace($pdo, $user);
    $input = mg_input();
    mg_require_csrf_for_write($input);
    $approvalMode = strtolower(trim((string)($input['approval_mode'] ?? 'advisory')));
    $outputType = strtolower(trim((string)($input['output_type'] ?? 'action_plan')));
    $agentMode = strtolower(trim((string)($input['mode'] ?? 'advisor')));
    if ($approvalMode === 'review_queue' || $outputType === 'admin_recommendation') {
        mg_agent_autonomy_require_for_merchant($pdo, (int)$user['id'], 'review_queue', 'Agent Review queue card creation');
    }
    if ($outputType === 'message_draft') {
        mg_agent_autonomy_require_for_merchant($pdo, (int)$user['id'], 'messages', 'agent message draft creation');
    }
    if ($agentMode === 'execute_plan') {
        mg_agent_autonomy_require_for_merchant($pdo, (int)$user['id'], 'review_queue', 'plan preparation');
    }
    mg_ok(mg_ai_chat_send_with_memory($pdo, $user, $input), 'Merchant agent reply created.', 201);
}

mg_fail('Method not allowed.', 405);
