<?php
declare(strict_types=1);

require_once __DIR__ . '/_intelligence.php';

mg_require_method('GET');
$user = mg_require_api_user();
$pdo = mg_db();

if (!mg_user_has_merchant_access($user, $pdo)) mg_fail('Merchant access required.', 403);

try {
    mg_rate_limit('merchant_canvas.intelligence', 'user:' . (int)$user['id'], 180, 60);
    $merchantUserId = (int)$user['id'];
    mg_ok([
        'settings' => mg_canvas_intel_settings($pdo, $merchantUserId),
        'zone_metrics' => mg_canvas_intel_zone_metrics($pdo, $merchantUserId),
        'activity' => mg_canvas_intel_activity($pdo, $merchantUserId, 40),
        'journeys' => mg_canvas_intel_journeys($pdo, $merchantUserId),
        'safety' => mg_canvas_intel_safety($pdo, $merchantUserId),
    ]);
} catch (RuntimeException $error) {
    mg_fail($error->getMessage(), 400);
} catch (Throwable $error) {
    mg_security_log('error', 'merchant_canvas.intelligence_failed', 'Store Canvas intelligence failed.', ['exception_class'=>$error::class], (int)$user['id']);
    mg_fail('Unable to load Store Canvas intelligence.', 500);
}
