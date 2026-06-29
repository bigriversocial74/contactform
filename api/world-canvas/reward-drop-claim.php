<?php
declare(strict_types=1);

require_once __DIR__ . '/_reward_drops.php';

mg_require_method('POST');
$input = mg_input();
mg_require_csrf_for_write($input);
$user = mg_require_api_user();
$pdo = mg_db();

try {
    mg_rate_limit('world_canvas.reward_drop_claim', 'user:' . (int)$user['id'], 80, 60);
    $result = mg_world_reward_drop_claim($pdo, $user, $input);
    mg_ok($result, 'Reward drop claimed.');
} catch (InvalidArgumentException|RuntimeException $error) {
    mg_fail($error->getMessage(), 400);
} catch (Throwable $error) {
    mg_security_log('error', 'world_canvas.reward_drop_claim_failed', 'World Canvas reward drop claim failed.', ['exception_class'=>$error::class], (int)($user['id'] ?? 0));
    mg_fail('Unable to claim World Canvas reward drop.', 500);
}
