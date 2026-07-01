<?php
/**
 * Delivery runs endpoint for World Canvas Target Drops.
 */
declare(strict_types=1);

require_once __DIR__ . '/_delivery_runs.php';

$pdo = mg_db();
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$user = $method === 'GET' ? mg_require_api_user() : mg_require_permission('merchant.locations.manage');

try {
    if ($method === 'GET') {
        mg_ok([
            'schema_ready' => mg_world_delivery_runs_ready($pdo),
            'delivery_runs' => mg_world_delivery_run_list($pdo, $user),
        ]);
    }

    if ($method !== 'POST') mg_fail('Method not allowed.', 405);

    $input = mg_input();
    mg_require_csrf_for_write($input);
    mg_rate_limit('world_canvas.delivery_runs', 'user:' . (int)($user['id'] ?? 0), 80, 60);

    $action = strtolower(trim((string)($input['action'] ?? 'test')));
    if ($action !== 'test' && $action !== 'live') throw new RuntimeException('Unsupported delivery run action.');

    $dropPublicId = trim((string)($input['target_drop_id'] ?? $input['drop_id'] ?? $input['id'] ?? ''));
    if ($dropPublicId === '') throw new RuntimeException('Target Drop is required.');

    $drop = mg_world_delivery_run_target_row($pdo, $dropPublicId, (int)($user['id'] ?? 0));
    if (!$drop) throw new RuntimeException('Target Drop not found.');

    $run = mg_world_delivery_run_create($pdo, $drop, $action === 'test' ? 'test' : 'live');
    if (!$run) throw new RuntimeException('Delivery runs table is not installed.');

    mg_ok(['delivery_run' => $run], $action === 'test' ? 'Test delivery run started.' : 'Live delivery run started.');
} catch (InvalidArgumentException|RuntimeException $error) {
    mg_fail($error->getMessage(), 400);
} catch (Throwable $error) {
    mg_security_log('error', 'world_canvas.delivery_runs_failed', 'Delivery run action failed.', ['exception_class' => $error::class], (int)($user['id'] ?? 0));
    mg_fail('Unable to start delivery run.', 500);
}
