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
    $sessionId = mg_store_safe_public_id($_GET['session_id'] ?? '', 'Store session');
    mg_rate_limit('merchant_canvas.customer_crm', 'user:' . (int)$user['id'], 240, 60);
    mg_ok(mg_store_customer_crm($pdo, (int)$user['id'], $sessionId));
} catch (InvalidArgumentException $error) {
    mg_fail($error->getMessage(), 422);
} catch (RuntimeException $error) {
    mg_fail($error->getMessage(), 404);
} catch (Throwable $error) {
    mg_security_log('error', 'merchant_canvas.customer_crm_failed', 'Merchant canvas customer CRM failed.', ['exception_class'=>$error::class], (int)$user['id']);
    mg_fail('Unable to load customer CRM.', 500);
}
