<?php
declare(strict_types=1);

require_once __DIR__ . '/_ai.php';
require_once dirname(__DIR__) . '/merchant/_merchant.php';
require_once dirname(__DIR__, 2) . '/includes/ai/merchant-agent-chat.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$pdo = mg_db();

if ($method === 'GET') {
    $user = mg_merchant_require_permission('merchant.ai.review');
    mg_merchant_ensure_workspace($pdo, $user);
    mg_ok(mg_ai_chat_public_state($pdo, (int) $user['id']));
}

if ($method === 'POST') {
    $user = mg_merchant_require_permission('merchant.ai.plan');
    mg_merchant_ensure_workspace($pdo, $user);
    $input = mg_input();
    mg_require_csrf_for_write($input);
    mg_ok(mg_ai_chat_send($pdo, $user, $input), 'Merchant agent reply created.', 201);
}

mg_fail('Method not allowed.', 405);
