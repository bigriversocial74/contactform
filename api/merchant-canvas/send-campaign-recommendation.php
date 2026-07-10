<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/store/_canvas_campaign_recommendations.php';

mg_require_method('POST');
$input = mg_input();
mg_require_csrf_for_write($input);
$user = mg_require_api_user();
$pdo = mg_db();

if (!mg_user_has_merchant_access($user, $pdo)) {
    mg_fail('Merchant access required.', 403);
}

try {
    mg_rate_limit('merchant_canvas.campaign_recommendation', 'user:' . (int)$user['id'], 60, 60);
    $result = mg_store_send_campaign_recommendation_notification(
        $pdo,
        $user,
        (string)($input['session_id'] ?? ''),
        (string)($input['campaign_id'] ?? ''),
        (string)($input['note'] ?? ''),
        (string)($input['idempotency_key'] ?? '')
    );
    mg_ok(
        ['recommendation' => $result],
        !empty($result['duplicate']) ? 'Campaign recommendation was already sent.' : 'Campaign recommendation notification sent.',
        !empty($result['duplicate']) ? 200 : 201
    );
} catch (InvalidArgumentException $error) {
    mg_fail($error->getMessage(), 422);
} catch (RuntimeException $error) {
    $message = strtolower($error->getMessage());
    $status = str_contains($message, 'do not message')
        || str_contains($message, 'already processing')
        || str_contains($message, 'expired')
        || str_contains($message, 'exhausted')
        || str_contains($message, 'within the last five minutes') ? 409 : (str_contains($message, 'setup is incomplete') ? 503 : 400);
    mg_fail($error->getMessage(), $status);
} catch (Throwable $error) {
    mg_security_log('error', 'merchant_canvas.campaign_recommendation_failed', 'Store Canvas campaign recommendation failed.', ['exception_class'=>$error::class], (int)$user['id']);
    mg_fail('Unable to send campaign recommendation.', 500);
}
