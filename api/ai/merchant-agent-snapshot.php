<?php
declare(strict_types=1);

require_once __DIR__ . '/_ai.php';
require_once dirname(__DIR__) . '/merchant/_merchant.php';
require_once dirname(__DIR__, 2) . '/includes/ai/merchant-agent-automatic-snapshot.php';

$pdo = mg_db();
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$access = mg_merchant_agent_require_owner_access($pdo);
$user = $access['user'];
$workspace = mg_merchant_ensure_workspace($pdo, $user);
$merchantId = (int)$user['id'];
if ((int)($workspace['merchant_user_id'] ?? $merchantId) !== $merchantId) {
    mg_fail('This Merchant Agent build is available to the merchant workspace owner only.', 403, ['scope'=>'merchant_owner_required']);
}

if ($method === 'GET') {
    $days = (int)($_GET['days'] ?? 30);
    mg_ok(['snapshot' => mg_merchant_agent_snapshot_ensure($pdo, $user, $days, 'workspace_load')]);
}

if ($method === 'POST') {
    $input = mg_input();
    mg_require_csrf_for_write($input);
    $days = (int)($input['days'] ?? 30);
    mg_ok(['snapshot' => mg_merchant_agent_snapshot_generate($pdo, $user, $days, 'manual_refresh')], 'Latest merchant snapshot refreshed.', 201);
}

mg_fail('Method not allowed.', 405);