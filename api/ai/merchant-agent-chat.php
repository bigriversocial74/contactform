<?php
declare(strict_types=1);

require_once __DIR__ . '/_ai.php';
require_once dirname(__DIR__) . '/merchant/_merchant.php';
require_once dirname(__DIR__, 2) . '/includes/merchant-automation-controls.php';
require_once dirname(__DIR__, 2) . '/includes/ai/merchant-agent-chat-memory.php';
require_once dirname(__DIR__, 2) . '/includes/ai/merchant-agent-admin-limits.php';

function mg_agent_chat_admin_operator(array $user): bool
{
    $roles = is_array($user['roles'] ?? null) ? $user['roles'] : [];
    $role = 'super_' . 'admin';
    return in_array($role, $roles, true);
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$pdo = mg_db();

if ($method === 'GET') {
    $user = mg_merchant_require_permission('merchant.ai.review');
    mg_merchant_ensure_workspace($pdo, $user);
    $state = mg_ai_chat_public_state($pdo, (int)$user['id']);
    $state['memory'] = mg_agent_memory_summary($pdo, (int)$user['id']);
    $state['agent_autonomy'] = mg_agent_autonomy_for_merchant($pdo, (int)$user['id']);
    $state['admin_operator_available'] = mg_agent_chat_admin_operator($user);
    $state['admin_ai_limits'] = mg_agent_admin_limit_public($pdo, (int)$user['id']);
    mg_ok($state);
}

if ($method === 'POST') {
    $input = mg_input();
    mg_require_csrf_for_write($input);
    $action = strtolower(trim((string)($input['action'] ?? 'send_message')));
    $localActions = ['save_agent_profile','save_memory_profile','create_thread','save_thread','archive_thread','clear_thread','rename_thread','load_thread'];

    if (!in_array($action, array_merge(['send_message'], $localActions), true)) {
        mg_fail('Unknown merchant agent chat action.', 422);
    }

    $user = mg_merchant_require_permission($action === 'send_message' ? 'merchant.ai.plan' : 'merchant.ai.review');
    mg_merchant_ensure_workspace($pdo, $user);
    $merchantId = (int)$user['id'];

    if ($action === 'save_agent_profile') {
        $profile = mg_agent_save_profile($pdo, $merchantId, $input);
        mg_ok(['agent_profile' => $profile, 'state' => mg_ai_chat_public_state($pdo, $merchantId)], 'Agent profile saved.');
    }

    if ($action === 'save_memory_profile') {
        $memory = mg_agent_memory_profile_save($pdo, $merchantId, $merchantId, $input);
        mg_ok(['memory' => $memory, 'state' => mg_ai_chat_public_state($pdo, $merchantId) + ['memory' => mg_agent_memory_summary($pdo, $merchantId)]], 'Merchant memory saved.');
    }

    if ($action === 'create_thread') {
        $thread = mg_agent_create_thread($pdo, $merchantId, ['title' => $input['title'] ?? 'Current chat'], true);
        mg_ok(['active_thread' => $thread, 'state' => mg_ai_chat_public_state($pdo, $merchantId)], 'New agent chat created.');
    }

    if (in_array($action, ['save_thread','archive_thread','clear_thread','rename_thread','load_thread'], true)) {
        $threadId = mg_ai_chat_clean($input['thread_id'] ?? '', 80);
        if ($action === 'load_thread') {
            $thread = mg_agent_thread_by_id($pdo, $merchantId, $threadId);
            if (!empty($thread['id']) && mg_agent_table_exists($pdo, 'merchant_agent_threads')) {
                $pdo->prepare("UPDATE merchant_agent_threads SET status='active',archived_at=NULL,updated_at=NOW() WHERE merchant_user_id=? AND public_id=?")->execute([$merchantId, $thread['id']]);
            }
        } else {
            $map = ['save_thread' => 'save', 'archive_thread' => 'archive', 'clear_thread' => 'clear', 'rename_thread' => 'rename'];
            mg_agent_thread_action($pdo, $merchantId, $threadId, $map[$action], $input);
        }
        mg_ok(['state' => mg_ai_chat_public_state($pdo, $merchantId)], 'Agent thread updated.');
    }

    $approvalMode = strtolower(trim((string)($input['approval_mode'] ?? 'advisory')));
    $outputType = strtolower(trim((string)($input['output_type'] ?? 'action_plan')));
    $agentMode = strtolower(trim((string)($input['mode'] ?? 'advisor')));
    $adminOperator = $approvalMode === 'admin_operator';

    if ($adminOperator) {
        if (!mg_agent_chat_admin_operator($user)) {
            mg_fail('Admin operator mode is not available for this account.', 403);
        }
        $input['mode'] = 'execute_plan';
        $input['approval_mode'] = 'review_queue';
        $input['admin_operator'] = true;
        $input['admin_autonomy_override'] = true;
        $agentMode = 'execute_plan';
        $approvalMode = 'review_queue';
    }

    if (!$adminOperator && ($approvalMode === 'review_queue' || $outputType === 'admin_recommendation')) {
        mg_agent_autonomy_require_for_merchant($pdo, $merchantId, 'review_queue', 'Agent Review queue card creation');
    }
    if (!$adminOperator && $outputType === 'message_draft') {
        mg_agent_autonomy_require_for_merchant($pdo, $merchantId, 'messages', 'agent message draft creation');
    }
    if (!$adminOperator && $agentMode === 'execute_plan') {
        mg_agent_autonomy_require_for_merchant($pdo, $merchantId, 'review_queue', 'plan preparation');
    }
    mg_agent_admin_limit_enforce_default($pdo, $merchantId);
    mg_ok(mg_ai_chat_send_with_memory($pdo, $user, $input), $adminOperator ? 'Admin operator agent plan created.' : 'Merchant agent reply created.', 201);
}

mg_fail('Method not allowed.', 405);
