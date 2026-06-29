<?php
declare(strict_types=1);

require_once __DIR__ . '/_reward_drops.php';

$user = mg_require_api_user();
$pdo = mg_db();
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

try {
    if ($method === 'POST') {
        $input = mg_input();
        mg_require_csrf_for_write($input);
        mg_rate_limit('world_canvas.reward_drop_create', 'user:' . (int)$user['id'], 40, 60);
        $drop = mg_world_reward_drop_create($pdo, $user, $input);
        mg_ok(['drop' => $drop], 'Reward drop created.');
    }

    if ($method === 'GET') {
        mg_rate_limit('world_canvas.reward_drop_list', 'user:' . (int)$user['id'], 180, 60);
        $drops = mg_world_reward_drop_list($pdo, $user, $_GET);
        mg_ok(['drops' => $drops]);
    }

    mg_fail('Method not allowed.', 405);
} catch (InvalidArgumentException|RuntimeException $error) {
    mg_fail($error->getMessage(), 400);
} catch (Throwable $error) {
    mg_security_log('error', 'world_canvas.reward_drops_failed', 'World Canvas reward drop endpoint failed.', ['exception_class'=>$error::class], (int)($user['id'] ?? 0));
    mg_fail('Unable to process World Canvas reward drop.', 500);
}
