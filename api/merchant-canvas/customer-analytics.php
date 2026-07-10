<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/store/_canvas_analytics.php';

mg_require_method('GET');
$user = mg_require_api_user();
$pdo = mg_db();

if (!mg_user_has_merchant_access($user, $pdo)) {
    mg_fail('Merchant access required.', 403);
}

try {
    $merchantUserId = (int)$user['id'];
    $sessionId = mg_store_safe_public_id($_GET['session_id'] ?? '', 'Store session');
    mg_rate_limit('merchant_canvas.customer_analytics', 'user:' . $merchantUserId, 180, 60);
    mg_ok(mg_store_analytics_customer_payload($pdo, $merchantUserId, $sessionId));
} catch (InvalidArgumentException $error) {
    mg_fail($error->getMessage(), 422);
} catch (RuntimeException $error) {
    $status = str_contains(strtolower($error->getMessage()), 'setup is incomplete') ? 503 : 404;
    mg_fail($error->getMessage(), $status);
} catch (Throwable $error) {
    mg_security_log('error', 'merchant_canvas.customer_analytics_failed', 'Merchant Canvas customer analytics failed.', ['exception_class' => $error::class], (int)$user['id']);
    mg_fail('Unable to load customer analytics.', 500);
}
