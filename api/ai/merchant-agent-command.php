<?php
declare(strict_types=1);

require_once __DIR__ . '/_ai.php';
require_once dirname(__DIR__) . '/merchant/_merchant.php';
require_once dirname(__DIR__, 2) . '/includes/ai/merchant-agent-credit-response.php';
require_once dirname(__DIR__, 2) . '/includes/ai/merchant-agent-command.php';

$pdo = mg_db();
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$access = mg_merchant_agent_require_owner_access($pdo);
$user = $access['user'];
$packageContext = $access['context'];
$workspace = mg_merchant_ensure_workspace($pdo, $user);
$merchantId = (int)$user['id'];
if ((int)($workspace['merchant_user_id'] ?? $merchantId) !== $merchantId) {
    mg_fail('This Merchant Agent build is available to the merchant workspace owner only.', 403, ['scope'=>'merchant_owner_required']);
}

if ($method === 'GET') {
    mg_merchant_agent_require_owner_permission($user, 'merchant.ai.review');
    $demo = !empty($_GET['demo']);
    mg_ok(array_merge(mg_agent_cmd_state($pdo, $user, $demo), mg_merchant_agent_ai_last_result($pdo, $user, $packageContext)));
}

if ($method === 'POST') {
    $input = mg_input();
    mg_require_csrf_for_write($input);
    $action = strtolower(trim((string)($input['action'] ?? 'state')));
    if ($action === 'save_goals') {
        mg_ok(array_merge(mg_agent_cmd_save_goals($pdo, $user, $input), mg_merchant_agent_ai_last_result($pdo, $user, $packageContext)), 'Agent goals saved.');
    }
    if ($action === 'daily_briefing') {
        mg_merchant_agent_ai_begin_call($pdo, $user, $packageContext, 'merchant_agent_command_briefing', ['merchant_owner_id'=>$merchantId]);
        try {
            $response = mg_agent_cmd_daily_briefing($pdo, $user, $input);
        } finally {
            mg_merchant_agent_ai_end_call();
        }
        mg_ok(array_merge($response, mg_merchant_agent_ai_last_result($pdo, $user, $packageContext)), 'Daily briefing created.', 201);
    }
    if ($action === 'create_package') {
        mg_merchant_agent_require_owner_permission($user, 'merchant.ai.review');
        mg_ok(array_merge(mg_agent_cmd_create_package($pdo, $user, $input), mg_merchant_agent_ai_last_result($pdo, $user, $packageContext)), 'Draft package sent to review.', 201);
    }
    mg_merchant_agent_require_owner_permission($user, 'merchant.ai.review');
    mg_ok(array_merge(mg_agent_cmd_state($pdo, $user, !empty($input['demo'])), mg_merchant_agent_ai_last_result($pdo, $user, $packageContext)));
}

mg_fail('Method not allowed.', 405);
