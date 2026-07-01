<?php
/**
 * Public interest capture for World Canvas Target Drops.
 */
declare(strict_types=1);

require_once __DIR__ . '/_target_drop_interests.php';

$pdo = mg_db();
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$user = mg_refresh_session_user();

try {
    if ($method !== 'POST') mg_fail('Method not allowed.', 405);
    $input = mg_input();
    mg_require_csrf_for_write($input);
    mg_rate_limit('world_canvas.target_drop_interest', 'ip:' . mg_client_ip(), 40, 60);
    if ($user && isset($user['id'])) mg_rate_limit('world_canvas.target_drop_interest_user', 'user:' . (int)$user['id'], 80, 60);
    $result = mg_world_target_drop_save_interest($pdo, $input, $user ?: null);
    mg_ok($result, 'Interest saved.');
} catch (InvalidArgumentException|RuntimeException $error) {
    mg_fail($error->getMessage(), 400);
} catch (Throwable $error) {
    mg_security_log('error', 'world_canvas.target_drop_interest_failed', 'Target Drop interest failed.', ['exception_class' => $error::class], isset($user['id']) ? (int)$user['id'] : null);
    mg_fail('Unable to save interest.', 500);
}
