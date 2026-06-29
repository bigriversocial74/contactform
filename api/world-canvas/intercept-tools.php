<?php
/**
 * Intercept tools endpoint for Campaign Drops.
 */
declare(strict_types=1);

require_once __DIR__ . '/_intercept_tools.php';

$pdo = mg_db();
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$user = mg_require_api_user();

try {
    if ($method === 'GET') {
        $userId = (int)($user['id'] ?? 0);
        mg_ok([
            'schema_ready' => mg_world_intercept_tools_ready($pdo),
            'catalog' => mg_world_intercept_catalog($pdo),
            'tools' => mg_world_intercept_user_tools($pdo, $userId, true),
        ]);
    }

    if ($method !== 'POST') mg_fail('Method not allowed.', 405);

    $input = mg_input();
    mg_require_csrf_for_write($input);
    mg_rate_limit('world_canvas.intercept_tools', 'user:' . (int)($user['id'] ?? 0), 40, 60);

    $action = strtolower(trim((string)($input['action'] ?? 'attempt')));
    if ($action === 'attempt') {
        $result = mg_world_intercept_attempt($pdo, $user, $input);
        mg_ok(['result' => $result], $result['status'] === 'success' ? 'Delivery intercepted.' : 'Intercept missed.');
    }

    mg_fail('Unsupported action.', 400);
} catch (InvalidArgumentException|RuntimeException $error) {
    mg_fail($error->getMessage(), 400);
} catch (Throwable $error) {
    mg_security_log('error', 'world_canvas.intercept_tools_failed', 'Intercept tools endpoint failed.', ['exception_class' => $error::class], (int)($user['id'] ?? 0));
    mg_fail('Unable to process intercept tool action.', 500);
}
