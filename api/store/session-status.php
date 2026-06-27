<?php
declare(strict_types=1);

require_once __DIR__ . '/_canvas.php';

mg_require_method('GET');
$pdo = mg_db();
$user = mg_refresh_session_user();
$viewerId = $user ? (int)$user['id'] : null;
$postId = trim((string)($_GET['post_id'] ?? ''));

try {
    $data = [
        'authenticated' => $viewerId !== null,
        'schema_ready' => mg_store_canvas_schema_ready($pdo),
        'active_session' => null,
        'post_state' => null,
    ];

    if ($viewerId !== null && mg_store_canvas_schema_ready($pdo)) {
        $data['active_session'] = mg_store_project_session(mg_store_active_session_for_customer($pdo, $viewerId));
    }

    if ($postId !== '') {
        $data['post_state'] = mg_store_feed_status_for_post($pdo, $viewerId, $postId);
    }

    mg_ok($data);
} catch (InvalidArgumentException $error) {
    mg_fail($error->getMessage(), 422);
} catch (RuntimeException $error) {
    mg_fail($error->getMessage(), 404);
} catch (Throwable $error) {
    mg_security_log('error', 'store_canvas.status_failed', 'Store session status failed.', ['exception_class'=>$error::class], $viewerId);
    mg_fail('Unable to load store session status.', 500);
}
