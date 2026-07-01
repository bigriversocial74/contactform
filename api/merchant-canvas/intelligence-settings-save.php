<?php
declare(strict_types=1);

require_once __DIR__ . '/_intelligence.php';

mg_require_method('POST');
$input = mg_input();
mg_require_csrf_for_write($input);
$user = mg_require_api_user();
$pdo = mg_db();

if (!mg_user_has_merchant_access($user, $pdo)) mg_fail('Merchant access required.', 403);

try {
    mg_rate_limit('merchant_canvas.intelligence_settings_save', 'user:' . (int)$user['id'], 80, 60);
    $settings = mg_canvas_intel_save_settings($pdo, (int)$user['id'], $input);
    mg_ok(['settings' => $settings], 'Store Canvas settings saved.');
} catch (RuntimeException|InvalidArgumentException $error) {
    mg_fail($error->getMessage(), 400);
} catch (Throwable $error) {
    mg_security_log('error', 'merchant_canvas.intelligence_settings_save_failed', 'Store Canvas intelligence settings save failed.', ['exception_class'=>$error::class], (int)$user['id']);
    mg_fail('Unable to save Store Canvas settings.', 500);
}
