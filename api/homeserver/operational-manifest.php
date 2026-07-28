<?php
declare(strict_types=1);

require_once __DIR__ . '/_operational_intelligence.php';

mg_require_method('GET');
$device = mg_homeserver_require_device('homeserver.operational.read');
$pdo = mg_db();

try {
    $manifest = mg_homeserver_operational_manifest($pdo, $device);
    if (mg_homeserver_operational_tables_ready($pdo)) {
        $payloadHash = hash('sha256', mg_homeserver_json($manifest));
        mg_homeserver_operational_record_receipt(
            $pdo,
            $device,
            '_manifest',
            'manifest',
            null,
            null,
            hash('sha256', 'microgifter|' . MG_HOMESERVER_OPERATIONAL_CONTRACT_VERSION . '|' . $payloadHash),
            count($manifest['datasets']),
            0,
            $payloadHash,
            'accepted',
            null,
            gmdate('Y-m-d H:i:s')
        );
    }
    mg_ok($manifest, 'Microgifter operational manifest ready.');
} catch (Throwable $error) {
    mg_security_log('error', 'homeserver.operational_manifest.failed', 'Unable to prepare HomeServer operational manifest.', [
        'device_id' => (string)($device['public_id'] ?? ''),
        'exception_class' => $error::class,
        'message' => $error->getMessage(),
    ], mg_homeserver_device_merchant_id($device));
    mg_fail('Unable to prepare HomeServer operational manifest.', 500);
}
