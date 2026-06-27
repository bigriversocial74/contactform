<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/store/_canvas.php';

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
    mg_rate_limit('merchant_canvas.send_message', 'user:' . (int)$user['id'], 90, 60);
    $message = mg_store_send_direct_message($pdo, (int)$user['id'], $sessionId, $body);
    mg_ok(['message' => $message], 'Message sent to customer inbox.');
} catch (InvalidArgumentException $error) {
    mg_fail($error->getMessage(), 422);
} catch (RuntimeException $error) {
    mg_fail($error->getMessage(), 400);
} catch (Throwable $error) {
    mg_security_log('error', 'merchant_canvas.send_message_failed', 'Merchant canvas direct message failed.', ['exception_class'=>$error::class], (int)$user['id']);
    mg_fail('Unable to send direct message.', 500);
}
