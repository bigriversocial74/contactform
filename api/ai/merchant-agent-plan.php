<?php
declare(strict_types=1);

require_once __DIR__ . '/_ai.php';
require_once dirname(__DIR__) . '/merchant/_merchant.php';
require_once dirname(__DIR__, 2) . '/includes/merchant-automation-controls.php';
require_once dirname(__DIR__, 2) . '/includes/ai/merchant-agent-credit-response.php';
require_once dirname(__DIR__, 2) . '/includes/ai/merchant-agent-planner.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$pdo = mg_db();
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
    $planId = strtolower(trim((string)($_GET['id'] ?? '')));
    if ($planId !== '') {
        if (strlen($planId) !== 36 || !preg_match('/^[a-f0-9-]{36}$/', $planId)) {
            mg_fail('Invalid plan identifier.', 422);
        }
        $stmt = $pdo->prepare(
            "SELECT p.*, ap.provider_key, m.model_key
             FROM ai_merchant_plans p
             INNER JOIN ai_providers ap ON ap.id = p.provider_id
             INNER JOIN ai_models m ON m.id = p.model_id
             WHERE p.public_id = ? AND p.merchant_user_id = ?
             LIMIT 1"
        );
        $stmt->execute([$planId, $merchantId]);
        $plan = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($plan)) mg_fail('AI plan not found.', 404);
        $items = mg_ai_context_rows($pdo, 'SELECT * FROM ai_merchant_plan_items WHERE plan_id = ? ORDER BY sequence_no ASC', [(int)$plan['id']], 100);
        mg_ok([
            'plan'=>mg_ai_merchant_public_plan($plan, $items),
            'agent_autonomy'=>mg_agent_autonomy_for_merchant($pdo, $merchantId),
            'ai_status'=>mg_merchant_agent_ai_status($pdo, $user, $packageContext),
        ]);
    }

    $limit = max(1, min(50, (int)($_GET['limit'] ?? 25)));
    $stmt = $pdo->prepare(
        "SELECT p.public_id id,p.scope,p.merchant_goal,p.status,p.priority,p.summary,p.input_tokens,p.output_tokens,p.created_at,p.updated_at,
                ap.provider_key,m.model_key,
                (SELECT COUNT(*) FROM ai_merchant_plan_items i WHERE i.plan_id = p.id) item_count
         FROM ai_merchant_plans p
         INNER JOIN ai_providers ap ON ap.id = p.provider_id
         INNER JOIN ai_models m ON m.id = p.model_id
         WHERE p.merchant_user_id = ?
         ORDER BY p.updated_at DESC,p.id DESC
         LIMIT {$limit}"
    );
    $stmt->execute([$merchantId]);
    mg_ok([
        'plans'=>$stmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
        'agent_autonomy'=>mg_agent_autonomy_for_merchant($pdo, $merchantId),
        'ai_status'=>mg_merchant_agent_ai_status($pdo, $user, $packageContext),
    ]);
}

if ($method === 'POST') {
    mg_merchant_agent_require_owner_permission($user, 'merchant.ai.plan');
    $input = mg_input();
    mg_require_csrf_for_write($input);
    mg_agent_autonomy_require_for_merchant($pdo, $merchantId, 'review_queue', 'AI plan creation');
    mg_merchant_agent_ai_begin_call($pdo, $user, $packageContext, 'merchant_agent_plan', [
        'scope'=>(string)($input['scope'] ?? 'all'),
        'merchant_owner_id'=>$merchantId,
    ]);
    try {
        $plan = mg_ai_merchant_create_plan($pdo, $user, $input);
    } finally {
        mg_merchant_agent_ai_end_call();
    }
    mg_ok(array_merge(['plan'=>$plan], mg_merchant_agent_ai_last_result($pdo, $user, $packageContext)), 'Merchant AI plan created.', 201);
}

mg_fail('Method not allowed.', 405);
