<?php
/**
 * Campaign Drops / Target Zones endpoint.
 */
declare(strict_types=1);

require_once __DIR__ . '/_target_drop_campaigns.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$user = $method === 'GET' ? mg_require_api_user() : mg_require_permission('merchant.locations.manage');
$pdo = mg_db();

try {
    if ($method === 'GET') {
        mg_ok([
            'schema_ready' => mg_world_target_drops_ready($pdo),
            'drops' => mg_world_target_drop_list($pdo, $user),
            'campaigns' => mg_world_target_drop_campaign_options($pdo, $user),
        ]);
    }

    if ($method !== 'POST') mg_fail('Method not allowed.', 405);

    $input = mg_input();
    mg_require_csrf_for_write($input);
    mg_rate_limit('world_canvas.target_drops', 'user:' . (int)($user['id'] ?? 0), 80, 60);
    $input = mg_world_target_drop_enrich_input_with_campaign($pdo, $user, $input);

    $action = strtolower(trim((string)($input['action'] ?? 'update')));
    if ($action === 'create') {
        $drop = mg_world_target_drop_create($pdo, $user, $input);
        mg_ok(['drop' => $drop], 'Target Drop draft created.');
    }

    if ($action === 'delete') {
        $drop = mg_world_target_drop_delete($pdo, $user, $input);
        mg_ok(['drop' => $drop], 'Target Drop deleted.');
    }

    if ($action === 'cancel') {
        $drop = mg_world_target_drop_set_status($pdo, $user, $input, 'cancelled');
        mg_ok(['drop' => $drop], 'Target Drop cancelled.');
    }

    if ($action === 'pause') {
        $drop = mg_world_target_drop_set_status($pdo, $user, $input, 'paused');
        mg_ok(['drop' => $drop], 'Target Drop paused.');
    }

    if ($action === 'complete') {
        $drop = mg_world_target_drop_set_status($pdo, $user, $input, 'completed');
        mg_ok(['drop' => $drop], 'Target Drop completed.');
    }

    if ($action === 'publish' || $action === 'schedule') {
        $drop = mg_world_target_drop_update($pdo, $user, $input, true);
        mg_ok(['drop' => $drop], $drop['status'] === 'scheduled' ? 'Target Drop scheduled.' : 'Target Drop launched.');
    }

    $drop = mg_world_target_drop_update($pdo, $user, $input, false);
    mg_ok(['drop' => $drop], 'Target Drop saved.');
} catch (InvalidArgumentException|RuntimeException $error) {
    mg_fail($error->getMessage(), 400);
} catch (Throwable $error) {
    mg_security_log('error', 'world_canvas.target_drops_failed', 'Target Drops endpoint failed.', ['exception_class' => $error::class], (int)($user['id'] ?? 0));
    mg_fail('Unable to save Target Drop.', 500);
}
