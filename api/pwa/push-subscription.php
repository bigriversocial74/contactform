<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/pwa-push.php';

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$user = mg_require_api_user();
$userId = (int)$user['id'];
$pdo = mg_db();

try {
    if ($method === 'GET') {
        mg_rate_limit('pwa.push.status', 'user:' . $userId, 120, 60);
        header('Cache-Control: private, no-store, max-age=0');
        header('Vary: Cookie, Authorization');
        mg_ok(mg_pwa_push_user_status($pdo, $userId), 'PWA push status loaded.');
    }

    if ($method === 'POST') {
        mg_rate_limit('pwa.push.subscription', 'user:' . $userId, 30, 300);
        $input = mg_input();
        mg_require_csrf_for_write($input);
        $action = strtolower(trim((string)($input['action'] ?? 'subscribe')));
        if (!in_array($action, ['subscribe', 'unsubscribe'], true)) {
            mg_fail('Invalid PWA subscription action.', 422);
        }
        if ($action === 'subscribe') {
            $subscription = is_array($input['subscription'] ?? null) ? $input['subscription'] : [];
            $result = mg_pwa_push_register_subscription($pdo, $userId, $subscription, (string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
            mg_audit('pwa.push.subscribe', 'notification', ['active_subscriptions' => $result['active_subscriptions']], $userId);
            mg_event('pwa.push.subscribe', ['active_subscriptions' => $result['active_subscriptions']], $userId);
            header('Cache-Control: private, no-store, max-age=0');
            mg_ok($result + ['status_payload' => mg_pwa_push_user_status($pdo, $userId)], 'PWA push subscription saved.');
        }

        $subscription = is_array($input['subscription'] ?? null) ? $input['subscription'] : null;
        $result = mg_pwa_push_deactivate_subscription($pdo, $userId, $subscription);
        mg_audit('pwa.push.unsubscribe', 'notification', ['active_subscriptions' => $result['active_subscriptions']], $userId);
        mg_event('pwa.push.unsubscribe', ['active_subscriptions' => $result['active_subscriptions']], $userId);
        header('Cache-Control: private, no-store, max-age=0');
        mg_ok($result + ['status_payload' => mg_pwa_push_user_status($pdo, $userId)], 'PWA push subscription revoked.');
    }

    mg_fail('Method not allowed.', 405);
} catch (InvalidArgumentException $error) {
    mg_fail($error->getMessage(), 422);
} catch (Throwable $error) {
    mg_security_log('error', 'pwa.push.subscription_failed', 'PWA push subscription request failed.', ['exception_class' => $error::class], $userId);
    mg_fail('Unable to update PWA push subscription.', 500);
}
