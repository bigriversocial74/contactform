<?php
declare(strict_types=1);

require_once __DIR__ . '/_canvas_runtime.php';

mg_require_method('POST');
$input = mg_input();
mg_require_csrf_for_write($input);
$user = mg_require_api_user();
$pdo = mg_db();

try {
    $postId = mg_store_safe_public_id($input['post_id'] ?? '', 'Post');
    $switchStore = !empty($input['switch_store']);
    mg_rate_limit('store.entry', 'user:' . (int)$user['id'], 60, 60);
    $result = mg_store_runtime_enter_post($pdo, (int)$user['id'], $postId, $switchStore);
    mg_ok($result, !empty($result['requires_confirmation']) ? 'Store switch confirmation required.' : 'Entered merchant store.');
} catch (InvalidArgumentException $error) {
    mg_fail($error->getMessage(), 422);
} catch (RuntimeException $error) {
    mg_fail($error->getMessage(), 400);
} catch (Throwable $error) {
    mg_security_log('error', 'store_canvas.entry_failed', 'Store canvas entry failed.', ['exception_class'=>$error::class], (int)$user['id']);
    mg_fail('Unable to enter merchant store.', 500);
}
