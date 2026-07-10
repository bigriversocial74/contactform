<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/store/_canvas_messaging.php';

mg_require_method('POST');
$input = mg_input();
mg_require_csrf_for_write($input);
$user = mg_require_api_user();
$pdo = mg_db();

if (!mg_user_has_merchant_access($user, $pdo)) {
    mg_fail('Merchant access required.', 403);
}

try {
    $sessionId = mg_store_safe_public_id($input['session_id'] ?? '', 'Store session');
    $body = $input['message'] ?? '';
    $idempotencyKey = mg_store_manual_ops_idempotency_key($input['idempotency_key'] ?? '');
    mg_rate_limit('merchant_canvas.send_message', 'user:' . (int)$user['id'], 90, 60);
    $message = mg_store_send_direct_message_via_messaging($pdo, (int)$user['id'], $sessionId, (string)$body, $idempotencyKey);
    mg_ok(
        ['message' => $message],
        !empty($message['duplicate']) ? 'Message already sent.' : 'Message sent through Messages and Notifications.',
        !empty($message['duplicate']) ? 200 : 201
    );
} catch (InvalidArgumentException $error) {
    mg_fail($error->getMessage(), 422);
} catch (RuntimeException $error) {
    $message = strtolower($error->getMessage());
    $status = str_contains($message, 'do not message') || str_contains($message, 'already processing') || str_contains($message, 'expired') ? 409 : (str_contains($message, 'setup is incomplete') ? 503 : 400);
    mg_fail($error->getMessage(), $status);
} catch (Throwable $error) {
    mg_security_log('error', 'merchant_canvas.send_message_failed', 'Merchant canvas direct message failed.', ['exception_class'=>$error::class], (int)$user['id']);
    mg_fail('Unable to send direct message.', 500);
}
