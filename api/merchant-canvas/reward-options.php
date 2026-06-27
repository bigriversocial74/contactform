<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/store/_canvas_rewards.php';

mg_require_method('GET');
$user = mg_require_api_user();
$pdo = mg_db();

if (!mg_user_has_merchant_access($user, $pdo)) {
    mg_fail('Merchant access required.', 403);
}

try {
    mg_rate_limit('merchant_canvas.reward_options', 'user:' . (int)$user['id'], 180, 60);
    mg_ok(mg_store_reward_options($pdo, (int)$user['id']));
} catch (RuntimeException $error) {
    mg_fail($error->getMessage(), 400);
} catch (Throwable $error) {
    mg_security_log('error', 'merchant_canvas.reward_options_failed', 'Merchant canvas reward options failed.', ['exception_class'=>$error::class], (int)$user['id']);
    mg_ok(['schema_ready'=>false,'campaigns'=>[],'templates'=>[],'can_send_reward'=>false], 'Reward options unavailable until campaign schema is ready.');
}
