<?php
declare(strict_types=1);

require_once __DIR__ . '/_package_changes.php';

mg_require_method('POST');
$user = mg_require_permission('subscriptions.admin');
$input = mg_input();
mg_require_csrf_for_write($input);

$requestId = trim((string)($input['request_id'] ?? $input['id'] ?? ''));
$action = trim((string)($input['action'] ?? ''));
$note = trim((string)($input['note'] ?? ''));

try {
    $pdo = mg_db();
    $request = mg_subscription_package_change_review($pdo, $requestId, $action, $user, $note);
    mg_ok(['request' => $request], 'Subscription package request reviewed.');
} catch (InvalidArgumentException $e) {
    mg_fail($e->getMessage(), 422);
} catch (Throwable $e) {
    mg_security_log('error', 'subscription.package_request_review_failed', 'Subscription package request review failed.', ['exception' => $e->getMessage(), 'request_id' => $requestId, 'action' => $action], (int)($user['id'] ?? 0));
    mg_fail('Unable to review subscription package request.', 500);
}
