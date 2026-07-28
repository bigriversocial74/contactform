<?php
declare(strict_types=1);

require_once __DIR__ . '/_operational_intelligence.php';

$user = mg_require_api_user();
$merchantId = (int)$user['id'];
$pdo = mg_db();
if (!mg_homeserver_operational_tables_ready($pdo)) mg_fail('HomeServer operational intelligence schema is not installed.', 503);

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$input = $method === 'GET' ? $_GET : mg_input();
$devicePublicId = strtolower(trim((string)($input['device_id'] ?? '')));
if (!mg_homeserver_is_uuid($devicePublicId)) mg_fail('HomeServer device identity is invalid.', 422);

$deviceStmt = $pdo->prepare('SELECT * FROM homeserver_devices WHERE public_id=? AND owner_user_id=? LIMIT 1');
$deviceStmt->execute([$devicePublicId, $merchantId]);
$device = $deviceStmt->fetch(PDO::FETCH_ASSOC);
if (!$device) mg_fail('HomeServer device not found.', 404);

if ($method === 'GET') {
    $manifest = mg_homeserver_operational_manifest($pdo, $device);
    mg_ok([
        'device' => mg_homeserver_device_payload($device),
        'datasets' => $manifest['datasets'],
        'provider_authoritative' => true,
        'raw_payment_credentials_exported' => false,
    ]);
}

if ($method !== 'POST') mg_fail('Method not allowed.', 405);
mg_require_csrf_for_write($input);

$datasetKey = strtolower(trim((string)($input['dataset_key'] ?? '')));
$definition = mg_homeserver_operational_catalog()[$datasetKey] ?? null;
if (!$definition) mg_fail('Operational dataset is not declared by the Microgifter provider.', 422);
$grantState = strtolower(trim((string)($input['grant_state'] ?? 'enabled')));
if (!in_array($grantState, ['enabled','paused','revoked'], true)) mg_fail('Dataset grant state is invalid.', 422);

$requestedUses = is_array($input['permitted_uses'] ?? null) ? array_values(array_unique(array_map(
    static fn($value): string => strtolower(trim((string)$value)),
    $input['permitted_uses']
))) : $definition['permitted_uses'];
$permittedUses = array_values(array_intersect($requestedUses, $definition['permitted_uses']));
if ($grantState === 'enabled' && $permittedUses === []) mg_fail('At least one permitted use is required.', 422);

$requestedFields = is_array($input['permitted_fields'] ?? null)
    ? array_values(array_unique(array_filter(array_map(static fn($value): string => trim((string)$value), $input['permitted_fields']), static fn(string $value): bool => preg_match('/^[a-zA-Z0-9_]{1,80}$/', $value) === 1)))
    : null;
$retentionDays = max(1, min(3650, (int)($input['retention_days'] ?? 365)));
$flags = [
    'include_message_bodies' => !empty($input['include_message_bodies']) ? 1 : 0,
    'include_contact_details' => !empty($input['include_contact_details']) ? 1 : 0,
    'include_purchase_history' => !empty($input['include_purchase_history']) ? 1 : 0,
    'include_gift_ownership' => !empty($input['include_gift_ownership']) ? 1 : 0,
];
foreach ($definition['required_grant_flags'] as $flag) {
    if ($grantState === 'enabled' && ($flags[$flag] ?? 0) !== 1) {
        mg_fail('This dataset requires an explicit sensitive-context permission.', 422, ['required_flag' => $flag]);
    }
}

$publicId = mg_homeserver_public_uuid();
$stmt = $pdo->prepare("INSERT INTO homeserver_dataset_grants
    (public_id,device_id,merchant_user_id,tenant_id,site_id,dataset_key,grant_state,classification,permitted_uses_json,permitted_fields_json,retention_days,include_message_bodies,include_contact_details,include_purchase_history,include_gift_ownership,approved_by_user_id,approved_at,revoked_at,created_at,updated_at)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,UTC_TIMESTAMP(),?,UTC_TIMESTAMP(),UTC_TIMESTAMP())
    ON DUPLICATE KEY UPDATE
      grant_state=VALUES(grant_state),classification=VALUES(classification),permitted_uses_json=VALUES(permitted_uses_json),permitted_fields_json=VALUES(permitted_fields_json),retention_days=VALUES(retention_days),include_message_bodies=VALUES(include_message_bodies),include_contact_details=VALUES(include_contact_details),include_purchase_history=VALUES(include_purchase_history),include_gift_ownership=VALUES(include_gift_ownership),approved_by_user_id=VALUES(approved_by_user_id),approved_at=UTC_TIMESTAMP(),revoked_at=VALUES(revoked_at),updated_at=UTC_TIMESTAMP()");
$stmt->execute([
    $publicId,
    (int)$device['id'],
    $merchantId,
    (string)$merchantId,
    null,
    $datasetKey,
    $grantState,
    $definition['classification'],
    mg_homeserver_json($permittedUses),
    $requestedFields === null ? null : mg_homeserver_json($requestedFields),
    $retentionDays,
    $flags['include_message_bodies'],
    $flags['include_contact_details'],
    $flags['include_purchase_history'],
    $flags['include_gift_ownership'],
    $merchantId,
    $grantState === 'revoked' ? gmdate('Y-m-d H:i:s') : null,
]);

mg_security_log('info', 'homeserver.dataset_grant.updated', 'HomeServer operational dataset grant updated.', [
    'device_id' => $devicePublicId,
    'dataset_key' => $datasetKey,
    'grant_state' => $grantState,
    'classification' => $definition['classification'],
    'permitted_uses' => $permittedUses,
    'retention_days' => $retentionDays,
    'sensitive_flags' => $flags,
], $merchantId);

mg_ok([
    'device_id' => $devicePublicId,
    'dataset_key' => $datasetKey,
    'grant_state' => $grantState,
    'classification' => $definition['classification'],
    'permitted_uses' => $permittedUses,
    'permitted_fields' => $requestedFields,
    'retention_days' => $retentionDays,
    'include_message_bodies' => (bool)$flags['include_message_bodies'],
    'include_contact_details' => (bool)$flags['include_contact_details'],
    'include_purchase_history' => (bool)$flags['include_purchase_history'],
    'include_gift_ownership' => (bool)$flags['include_gift_ownership'],
], 'Operational dataset grant saved.');
