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
    mg_rate_limit('merchant_canvas.rule_simulator', 'user:' . (int)$user['id'], 120, 60);
    $result = mg_canvas_intel_simulate($pdo, (int)$user['id'], $input);
    mg_ok(['simulation' => $result], $result['label'] ?? 'Simulation complete.');
} catch (RuntimeException|InvalidArgumentException $error) {
    mg_fail($error->getMessage(), 400);
} catch (Throwable $error) {
    mg_security_log('error', 'merchant_canvas.rule_simulator_failed', 'Store Canvas rule simulator failed.', ['exception_class'=>$error::class], (int)$user['id']);
    mg_fail('Unable to run Store Canvas rule simulation.', 500);
}
