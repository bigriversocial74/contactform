<?php
declare(strict_types=1);

require_once __DIR__ . '/_opportunities.php';

mg_require_method('GET');
$user = mg_require_api_user();
$pdo = mg_db();

try {
    mg_rate_limit('world_canvas.opportunities', 'user:' . (int)$user['id'], 180, 60);
    mg_ok(mg_world_opportunities_payload($pdo, $user, $_GET));
} catch (InvalidArgumentException|RuntimeException $error) {
    mg_fail($error->getMessage(), 400);
} catch (Throwable $error) {
    mg_security_log('error', 'world_canvas.opportunities_failed', 'World Canvas opportunities failed.', ['exception_class'=>$error::class], (int)($user['id'] ?? 0));
    mg_fail('Unable to load World Canvas opportunities.', 500);
}
