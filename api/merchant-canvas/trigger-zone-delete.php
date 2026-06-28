<?php
declare(strict_types=1);

require_once __DIR__ . '/_trigger_zones.php';

mg_require_method('POST');
$input = mg_input();
mg_require_csrf_for_write($input);
$user = mg_require_api_user();
$pdo = mg_db();

if (!mg_user_has_merchant_access($user, $pdo)) {
    mg_fail('Merchant access required.', 403);
}

try {
    mg_rate_limit('merchant_canvas.trigger_zone_delete', 'user:' . (int)$user['id'], 90, 60);
    $zoneId = mg_store_safe_public_id($input['id'] ?? '', 'Trigger zone');
    mg_canvas_trigger_zone_archive($pdo, (int)$user['id'], $zoneId);
    mg_ok(['id'=>$zoneId,'zones'=>mg_canvas_trigger_zone_list($pdo, (int)$user['id'])], 'Trigger zone deleted.');
} catch (InvalidArgumentException $error) {
    mg_fail($error->getMessage(), 422);
} catch (RuntimeException $error) {
    mg_fail($error->getMessage(), 400);
} catch (Throwable $error) {
    mg_security_log('error', 'merchant_canvas.trigger_zone_delete_failed', 'Store Canvas trigger zone delete failed.', ['exception_class'=>$error::class], (int)$user['id']);
    mg_fail('Unable to delete trigger zone.', 500);
}
