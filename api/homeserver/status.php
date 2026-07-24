<?php
declare(strict_types=1);

require_once __DIR__ . '/_homeserver.php';

mg_require_method('GET');
$device = mg_homeserver_require_device('homeserver.status');

mg_ok([
    'device' => mg_homeserver_device_payload($device),
    'cloud_time_utc' => gmdate(DATE_ATOM),
    'authority' => [
        'cloud' => ['identity', 'payments', 'purchases', 'campaigns', 'rewards', 'wallet', 'pppm', 'microgifts', 'claims', 'redemption', 'shared_permissions', 'central_audit'],
        'homeserver' => ['local_configuration', 'local_keys', 'local_models', 'private_documents', 'local_integrations', 'local_automations', 'backups', 'diagnostics', 'queued_work_before_acceptance'],
    ],
    'sync_policy' => [
        'max_operations_per_request' => MG_HOMESERVER_MAX_SYNC_OPERATIONS,
        'retry_safe' => true,
        'idempotency_required' => true,
        'commerce_mutations_allowed' => false,
    ],
]);
