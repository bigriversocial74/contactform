<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/pwa-push.php';

mg_require_method('POST');
$user = mg_require_api_user();
$userId = (int)$user['id'];
mg_rate_limit('pwa.notification.open', 'user:' . $userId, 120, 60);
$input = mg_input();
$notificationId = trim((string)($input['notification_id'] ?? ''));

try {
    $result = mg_pwa_push_mark_opened(mg_db(), $userId, $notificationId);
    header('Cache-Control: private, no-store, max-age=0');
    mg_ok($result, 'PWA notification open state recorded.');
} catch (Throwable $error) {
    mg_security_log('warning', 'pwa.notification.open_failed', 'Unable to record PWA notification open state.', ['exception_class' => $error::class], $userId);
    mg_fail('Unable to record notification open state.', 500);
}
