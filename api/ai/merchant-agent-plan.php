<?php
declare(strict_types=1);

require_once __DIR__ . '/_ai.php';
require_once dirname(__DIR__) . '/merchant/_merchant.php';
require_once dirname(__DIR__, 2) . '/includes/merchant-automation-controls.php';
require_once dirname(__DIR__, 2) . '/includes/ai/merchant-agent-planner.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$pdo = mg_db();

if ($method === 'GET') {
    $user = mg_merchant_require_permission('merchant.ai.review');
    mg_merchant_ensure_workspace($pdo, $user);
    $merchantId = (int) $user['id'];

    $planId = strtolower(trim((string) ($_GET['id'] ?? '')));
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
        if (!is_array($plan)) {
            mg_fail('AI plan not found.', 404);
        }

        $items = mg_ai_context_rows(
            $pdo,
            'SELECT * FROM ai_merchant_plan_items WHERE plan_id = ? ORDER BY sequence_no ASC',
            [(int) $plan['id']],
            100
        );
        mg_ok(['plan' => mg_ai_merchant_public_plan($plan, $items), 'agent_autonomy' => mg_agent_autonomy_for_merchant($pdo, $merchantId)]);
    }

    $limit = max(1, min(50, (int) ($_GET['limit'] ?? 25)));
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
    mg_ok(['plans' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], 'agent_autonomy' => mg_agent_autonomy_for_merchant($pdo, $merchantId)]);
}

if ($method === 'POST') {
    $user = mg_merchant_require_permission('merchant.ai.plan');
    $input = mg_input();
    mg_require_csrf_for_write($input);
    mg_agent_autonomy_require_for_merchant($pdo, (int)$user['id'], 'review_queue', 'AI plan creation');
    $plan = mg_ai_merchant_create_plan($pdo, $user, $input);
    mg_ok(['plan' => $plan], 'Merchant AI plan created.', 201);
}

mg_fail('Method not allowed.', 405);
