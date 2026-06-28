<?php
declare(strict_types=1);

require_once __DIR__ . '/_ai.php';
require_once dirname(__DIR__) . '/merchant/_merchant.php';
require_once dirname(__DIR__, 2) . '/includes/merchant-automation-controls.php';
require_once dirname(__DIR__, 2) . '/includes/ai/merchant-plan-actions.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if ($method !== 'POST') {
    mg_fail('Method not allowed.', 405);
}

$user = mg_merchant_require_permission('merchant.ai.review');
$pdo = mg_db();
mg_merchant_ensure_workspace($pdo, $user);

$input = mg_input();
mg_require_csrf_for_write($input);

$decision = strtolower(trim((string)($input['decision'] ?? 'approve')));
if ($decision === 'approve') {
    $itemPublicId = strtolower(trim((string)($input['item_id'] ?? '')));
    if (strlen($itemPublicId) !== 36 || !preg_match('/^[a-f0-9-]{36}$/', $itemPublicId)) {
        mg_fail('Invalid recommendation identifier.', 422);
    }
    $stmt = $pdo->prepare('SELECT i.action_key FROM ai_merchant_plan_items i INNER JOIN ai_merchant_plans p ON p.id=i.plan_id WHERE i.public_id=? AND p.merchant_user_id=? LIMIT 1');
    $stmt->execute([$itemPublicId, (int)$user['id']]);
    $actionKey = (string)($stmt->fetchColumn() ?: '');
    if ($actionKey === '') mg_fail('AI recommendation not found.', 404);
    mg_agent_autonomy_require_action_key($pdo, (int)$user['id'], $actionKey);
}

$item = mg_ai_plan_review_item($pdo, $user, $input);
mg_ok(['item' => $item], 'AI recommendation reviewed.');
