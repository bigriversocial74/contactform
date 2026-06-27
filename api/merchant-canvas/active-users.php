<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/store/_canvas.php';

mg_require_method('GET');
$user = mg_require_api_user();
$pdo = mg_db();

if (!mg_user_has_merchant_access($user, $pdo)) {
    mg_fail('Merchant access required.', 403);
}

try {
    mg_rate_limit('merchant_canvas.active_users', 'user:' . (int)$user['id'], 240, 60);
    $customers = mg_store_active_customers_for_merchant($pdo, (int)$user['id']);
    mg_ok([
        'customers' => $customers,
        'summary' => [
            'active_customers' => count($customers),
            'agent_status' => 'Watching store canvas',
            'message_enabled' => true,
        ],
    ]);
} catch (RuntimeException $error) {
    mg_fail($error->getMessage(), 400);
} catch (Throwable $error) {
    mg_security_log('error', 'merchant_canvas.active_users_failed', 'Merchant canvas active users failed.', ['exception_class'=>$error::class], (int)$user['id']);
    mg_fail('Unable to load active customers.', 500);
}
