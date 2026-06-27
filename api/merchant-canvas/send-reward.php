<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/store/_canvas_rewards.php';

mg_require_method('POST');
$input = mg_input();
mg_require_csrf_for_write($input);
$user = mg_require_api_user();
$pdo = mg_db();

if (!mg_user_has_merchant_access($user, $pdo)) {
    mg_fail('Merchant access required.', 403);
}

try {
    $sessionId = mg_store_safe_public_id($input['session_id'] ?? '', 'Store session');
    $campaignId = mg_store_safe_public_id($input['campaign_id'] ?? '', 'Campaign');
    $templateId = trim((string)($input['reward_template_id'] ?? $input['template_id'] ?? ''));
    $templateId = $templateId !== '' ? mg_store_safe_public_id($templateId, 'Reward template') : null;
    $note = $input['note'] ?? '';
    $expirationDays = $input['expiration_days'] ?? null;
    $expiresAt = $input['expires_at'] ?? null;
    $idempotencyKey = trim((string)($input['idempotency_key'] ?? ''));

    mg_rate_limit('merchant_canvas.send_reward', 'user:' . (int)$user['id'], 60, 60);
    $reward = mg_store_reward_issue($pdo, $user, $sessionId, $campaignId, $templateId, (string)$note, $expirationDays, $expiresAt, $idempotencyKey);
    mg_ok(['reward' => $reward], $reward['duplicate'] ? 'Reward already issued.' : 'Reward sent to customer IN/OUT Box.', $reward['duplicate'] ? 200 : 201);
} catch (InvalidArgumentException $error) {
    mg_fail($error->getMessage(), 422);
} catch (RuntimeException $error) {
    $status = str_contains(strtolower($error->getMessage()), 'limit') || str_contains(strtolower($error->getMessage()), 'already') ? 409 : 400;
    mg_fail($error->getMessage(), $status);
} catch (Throwable $error) {
    mg_security_log('error', 'merchant_canvas.send_reward_failed', 'Merchant canvas reward send failed.', ['exception_class'=>$error::class,'message'=>$error->getMessage()], (int)$user['id']);
    mg_fail('Unable to send reward.', 500);
}
