<?php
declare(strict_types=1);

require_once __DIR__ . '/_package_changes.php';

$user = mg_require_permission('subscriptions.admin');
$status = trim((string)($_GET['status'] ?? 'pending'));
$limit = max(1, min(100, (int)($_GET['limit'] ?? 50)));

try {
    $pdo = mg_db();
    $requests = mg_subscription_package_change_admin_list($pdo, $status, $limit);
    mg_ok(['requests' => $requests, 'status' => $status], 'Subscription package requests loaded.');
} catch (Throwable $e) {
    mg_security_log('error', 'subscription.package_requests_list_failed', 'Subscription package request list failed.', ['exception' => $e->getMessage()], (int)($user['id'] ?? 0));
    mg_fail('Unable to load subscription package requests.', 500);
}
