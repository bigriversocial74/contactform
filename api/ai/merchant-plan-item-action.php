<?php
declare(strict_types=1);

require_once __DIR__ . '/_ai.php';
require_once dirname(__DIR__) . '/merchant/_merchant.php';
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

$item = mg_ai_plan_review_item($pdo, $user, $input);
mg_ok(['item' => $item], 'AI recommendation reviewed.');
