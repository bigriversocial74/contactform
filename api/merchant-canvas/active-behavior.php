<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/store/_canvas_behavior_memory.php';

mg_require_method('GET');
$user = mg_require_api_user();
$pdo = mg_db();

if (!mg_user_has_merchant_access($user, $pdo)) {
    mg_fail('Merchant access required.', 403);
}

try {
    $merchantUserId = (int)$user['id'];
    mg_rate_limit('merchant_canvas.active_behavior', 'user:' . $merchantUserId, 90, 60);
    mg_ok(mg_store_behavior_active_profiles($pdo, $merchantUserId));
} catch (RuntimeException $error) {
    $status = str_contains(strtolower($error->getMessage()), 'setup is incomplete') ? 503 : 400;
    mg_fail($error->getMessage(), $status);
} catch (Throwable $error) {
    mg_security_log('error', 'merchant_canvas.active_behavior_failed', 'Store Canvas active behavior profiles failed.', ['exception_class' => $error::class], (int)$user['id']);
    mg_fail('Unable to load active customer behavior profiles.', 500);
}
