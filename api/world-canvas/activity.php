<?php
/**
 * World Canvas activity read endpoint.
 */
declare(strict_types=1);

require_once __DIR__ . '/_world.php';
require_once __DIR__ . '/_viewer_nodes.php';

mg_require_method('GET');
$user = mg_require_api_user();
$pdo = mg_db();

try {
    mg_rate_limit('world_canvas.activity', 'user:' . (int) $user['id'], 180, 60);
    $payload = mg_world_canvas_payload($pdo, $user);
    $payload = mg_world_canvas_merge_viewer_nodes($payload, mg_world_canvas_viewer_nodes($pdo, $user));
    mg_ok($payload);
} catch (RuntimeException $error) {
    mg_fail($error->getMessage(), 400);
} catch (Throwable $error) {
    mg_security_log('error', 'world_canvas.activity_failed', 'World Canvas activity failed.', ['exception_class' => $error::class], (int) ($user['id'] ?? 0));
    mg_fail('Unable to load World Canvas activity.', 500);
}