<?php
declare(strict_types=1);

require_once __DIR__ . '/_operational_intelligence.php';

mg_require_method('POST');
$device = mg_homeserver_require_device('homeserver.campaigns.execute');
$pdo = mg_db();
$input = mg_homeserver_input();

try {
    mg_ok(mg_homeserver_campaign_action($pdo, $device, $input), 'HomeServer campaign action processed.');
} catch (Throwable $error) {
    mg_security_log('warning', 'homeserver.campaign_action.failed', 'HomeServer campaign action failed.', [
        'device_id' => (string)($device['public_id'] ?? ''),
        'action_type' => (string)($input['action_type'] ?? ''),
        'campaign_type' => (string)($input['campaign_type'] ?? ''),
        'campaign_id' => (string)($input['campaign_id'] ?? ''),
        'contact_id' => (string)($input['contact_id'] ?? ''),
        'exception_class' => $error::class,
        'message' => $error->getMessage(),
    ], mg_homeserver_device_merchant_id($device));
    throw $error;
}
