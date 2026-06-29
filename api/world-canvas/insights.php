<?php
declare(strict_types=1);

require_once __DIR__ . '/_insights.php';

mg_require_method('GET');
$user = mg_require_api_user();
$pdo = mg_db();

try {
    mg_rate_limit('world_canvas.insights', 'user:' . (int)$user['id'], 180, 60);
    $payload = mg_world_insights_payload($pdo, $user, $_GET);
    mg_ok($payload);
} catch (InvalidArgumentException|RuntimeException $error) {
    mg_fail($error->getMessage(), 400);
} catch (Throwable $error) {
    mg_security_log('error', 'world_canvas.insights_failed', 'World Canvas insights failed.', ['exception_class'=>$error::class], (int)($user['id'] ?? 0));
    mg_fail('Unable to load World Canvas insights.', 500);
}
