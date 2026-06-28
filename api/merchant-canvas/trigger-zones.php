<?php
declare(strict_types=1);

require_once __DIR__ . '/_trigger_zones.php';

mg_require_method('GET');
$user = mg_require_api_user();
$pdo = mg_db();

if (!mg_user_has_merchant_access($user, $pdo)) {
    mg_fail('Merchant access required.', 403);
}

try {
    mg_rate_limit('merchant_canvas.trigger_zones', 'user:' . (int)$user['id'], 180, 60);
    $schemaReady = mg_canvas_trigger_zone_schema_ready($pdo);
    mg_ok([
        'schema_ready' => $schemaReady,
        'zones' => $schemaReady ? mg_canvas_trigger_zone_list($pdo, (int)$user['id']) : [],
        'priority_levels' => [1,2,3,4,5],
    ]);
} catch (Throwable $error) {
    mg_security_log('error', 'merchant_canvas.trigger_zones_failed', 'Store Canvas trigger zones failed.', ['exception_class'=>$error::class,'message'=>$error->getMessage()], (int)$user['id']);
    mg_ok(['schema_ready'=>false,'zones'=>[],'priority_levels'=>[1,2,3,4,5]], 'Trigger zones unavailable until migration is applied.');
}
