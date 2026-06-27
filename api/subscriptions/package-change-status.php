<?php
declare(strict_types=1);

require_once __DIR__ . '/_package_changes.php';
require_once dirname(__DIR__, 2) . '/includes/package-entitlements.php';

mg_require_method('GET');
$user = mg_require_api_user();

try {
    $pdo = mg_db();
    $request = mg_subscription_package_change_latest($pdo, (int)$user['id'], false);
    $context = mg_user_package_context($pdo, $user);
    $publicRequest = $request ? mg_subscription_package_change_public($request) : null;
    $state = !empty($context['merchant_access']) ? 'active_access' : 'free_access';
    if (empty($context['merchant_access']) && $publicRequest && ($publicRequest['status'] ?? '') === 'pending_payment') $state = 'payment_pending';
    if (empty($context['merchant_access']) && $publicRequest && ($publicRequest['status'] ?? '') === 'pending_admin_review') $state = 'review_pending';

    mg_ok([
        'request' => $publicRequest,
        'package' => [
            'package_id' => (string)($context['package_id'] ?? 'free'),
            'package_name' => (string)($context['package_name'] ?? 'Free'),
            'status' => (string)($context['status'] ?? 'free'),
            'merchant_access' => !empty($context['merchant_access']),
            'is_paid' => !empty($context['is_paid']),
        ],
        'activation' => [
            'state' => $state,
            'workspace_access' => !empty($context['merchant_access']),
        ],
    ], 'Package change status loaded.');
} catch (Throwable $e) {
    mg_security_log('error', 'subscription.package_change_status_failed', 'Subscription package change status failed.', ['exception' => $e->getMessage()], (int)($user['id'] ?? 0));
    mg_fail('Unable to load package change status.', 500);
}
