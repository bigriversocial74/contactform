<?php
declare(strict_types=1);

require_once __DIR__ . '/_operational_intelligence.php';

mg_require_method('POST');
$device = mg_homeserver_require_device('homeserver.operational.read');
$pdo = mg_db();
$input = mg_homeserver_input();

try {
    mg_ok(mg_homeserver_operational_export($pdo, $device, $input), 'Microgifter operational evidence exported.');
} catch (Throwable $error) {
    mg_security_log('warning', 'homeserver.operational_export.failed', 'HomeServer operational export failed.', [
        'device_id' => (string)($device['public_id'] ?? ''),
        'dataset_key' => (string)($input['dataset_key'] ?? ''),
        'mode' => (string)($input['mode'] ?? ''),
        'exception_class' => $error::class,
        'message' => $error->getMessage(),
    ], mg_homeserver_device_merchant_id($device));
    throw $error;
}
