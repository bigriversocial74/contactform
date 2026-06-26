<?php
declare(strict_types=1);

require_once __DIR__ . '/_package_changes.php';

mg_require_method('GET');
$user = mg_require_api_user();

try {
    $pdo = mg_db();
    $request = mg_subscription_package_change_latest($pdo, (int)$user['id'], false);
    mg_ok(['request' => $request ? mg_subscription_package_change_public($request) : null], 'Package change status loaded.');
} catch (Throwable $e) {
    mg_security_log('error', 'subscription.package_change_status_failed', 'Subscription package change status failed.', ['exception' => $e->getMessage()], (int)($user['id'] ?? 0));
    mg_fail('Unable to load package change status.', 500);
}
