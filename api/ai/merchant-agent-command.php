<?php
declare(strict_types=1);

require_once __DIR__ . '/_ai.php';
require_once dirname(__DIR__) . '/merchant/_merchant.php';
require_once dirname(__DIR__, 2) . '/includes/ai/merchant-agent-command.php';

$pdo = mg_db();
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$user = mg_merchant_require_permission('merchant.ai.review');
mg_merchant_ensure_workspace($pdo, $user);

if ($method === 'GET') {
    $demo = !empty($_GET['demo']);
    mg_ok(mg_agent_cmd_state($pdo, $user, $demo));
}

if ($method === 'POST') {
    $input = mg_input();
    mg_require_csrf_for_write($input);
    $action = strtolower(trim((string) ($input['action'] ?? 'state')));
    if ($action === 'save_goals') mg_ok(mg_agent_cmd_save_goals($pdo, $user, $input), 'Agent goals saved.');
    if ($action === 'daily_briefing') mg_ok(mg_agent_cmd_daily_briefing($pdo, $user, $input), 'Daily briefing created.', 201);
    if ($action === 'create_package') mg_ok(mg_agent_cmd_create_package($pdo, $user, $input), 'Draft package sent to review.', 201);
    mg_ok(mg_agent_cmd_state($pdo, $user, !empty($input['demo'])));
}

mg_fail('Method not allowed.', 405);
