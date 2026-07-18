<?php
declare(strict_types=1);

require_once __DIR__ . '/_ai.php';
require_once dirname(__DIR__) . '/merchant/_merchant.php';
require_once dirname(__DIR__, 2) . '/includes/merchant-automation-controls.php';
require_once dirname(__DIR__, 2) . '/includes/ai/merchant-agent-credit-response.php';
require_once dirname(__DIR__, 2) . '/includes/ai/merchant-agent-chat.php';

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    mg_fail('Method not allowed.', 405);
}

$pdo = mg_db();
$access = mg_merchant_agent_require_owner_access($pdo);
$user = $access['user'];
$packageContext = $access['context'];
$workspace = mg_merchant_ensure_workspace($pdo, $user);
$merchantId = (int)$user['id'];
if ((int)($workspace['merchant_user_id'] ?? $merchantId) !== $merchantId) {
    mg_fail('This Merchant Agent build is available to the merchant workspace owner only.', 403, ['scope'=>'merchant_owner_required']);
}
mg_merchant_agent_require_owner_permission($user, 'merchant.ai.review');
mg_agent_autonomy_require_for_merchant($pdo, $merchantId, 'review_queue', 'agent review item creation');
$input = mg_input();
mg_require_csrf_for_write($input);
$response = mg_ai_chat_bridge_to_review($pdo, $user, $input);
if (is_array($response['state'] ?? null)) {
    $response['state'] = mg_merchant_agent_state_with_access($pdo, $user, $packageContext, $response['state']);
}
mg_ok(array_merge($response, mg_merchant_agent_ai_last_result($pdo, $user, $packageContext)), 'Agent card added to review queue.', 201);
