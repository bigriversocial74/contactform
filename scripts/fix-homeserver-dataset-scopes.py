#!/usr/bin/env python3
from pathlib import Path

path = Path("api/homeserver/_operational_intelligence.php")
text = path.read_text(encoding="utf-8")

anchor = '''function mg_homeserver_operational_grant(PDO $pdo, array $device, string $datasetKey): array
'''
helpers = r'''function mg_homeserver_operational_required_scope(string $datasetKey): string
{
    if (str_starts_with($datasetKey, 'reviews.')) return 'homeserver.reviews.read';
    if (str_starts_with($datasetKey, 'conversations.')) return 'homeserver.messages.read';
    if (str_starts_with($datasetKey, 'crm.')) return 'homeserver.crm.read';
    if (str_starts_with($datasetKey, 'commerce.')) return 'homeserver.commerce_history.read';
    if (str_starts_with($datasetKey, 'gifts.')) return 'homeserver.gifts.read';
    if (str_starts_with($datasetKey, 'campaigns.') || str_starts_with($datasetKey, 'creator.')) return 'homeserver.campaigns.read';
    return 'homeserver.operational.read';
}

function mg_homeserver_operational_device_has_scope(array $device, string $scope): bool
{
    $scopes = json_decode((string)($device['scopes_json'] ?? '[]'), true);
    return is_array($scopes) && in_array($scope, $scopes, true);
}

function mg_homeserver_operational_require_dataset_scope(array $device, string $datasetKey): string
{
    $scope = mg_homeserver_operational_required_scope($datasetKey);
    if (!mg_homeserver_operational_device_has_scope($device, $scope)) {
        mg_fail('The paired HomeServer does not have the device scope required for this dataset.', 403, [
            'dataset_key' => $datasetKey,
            'required_scope' => $scope,
        ]);
    }
    return $scope;
}

function mg_homeserver_operational_grant(PDO $pdo, array $device, string $datasetKey): array
'''
if "function mg_homeserver_operational_required_scope" not in text:
    if text.count(anchor) != 1:
        raise SystemExit("operational grant helper anchor was not found")
    text = text.replace(anchor, helpers, 1)

old_grant = '''    if (!isset(mg_homeserver_operational_catalog()[$datasetKey])) mg_fail('Operational dataset is not declared by the Microgifter provider.', 422);
    if (!mg_homeserver_operational_tables_ready($pdo)) mg_fail('HomeServer operational intelligence schema is not installed.', 503);
'''
new_grant = '''    if (!isset(mg_homeserver_operational_catalog()[$datasetKey])) mg_fail('Operational dataset is not declared by the Microgifter provider.', 422);
    mg_homeserver_operational_require_dataset_scope($device, $datasetKey);
    if (!mg_homeserver_operational_tables_ready($pdo)) mg_fail('HomeServer operational intelligence schema is not installed.', 503);
'''
if "mg_homeserver_operational_require_dataset_scope($device, $datasetKey);" not in text:
    if text.count(old_grant) != 1:
        raise SystemExit("operational grant scope anchor was not found")
    text = text.replace(old_grant, new_grant, 1)

old_manifest = '''            'required_grant_flags' => $definition['required_grant_flags'],
            'available' => $availableSources !== [],
'''
new_manifest = '''            'required_grant_flags' => $definition['required_grant_flags'],
            'required_device_scope' => mg_homeserver_operational_required_scope($key),
            'device_scope_allowed' => mg_homeserver_operational_device_has_scope($device, mg_homeserver_operational_required_scope($key)),
            'available' => $availableSources !== [],
'''
if "'required_device_scope'" not in text:
    if text.count(old_manifest) != 1:
        raise SystemExit("operational manifest scope anchor was not found")
    text = text.replace(old_manifest, new_manifest, 1)

path.write_text(text, encoding="utf-8", newline="\n")

validator_path = Path("scripts/validate_homeserver_operational_intelligence_v1.py")
validator = validator_path.read_text(encoding="utf-8")
marker = '''    "mg_homeserver_operational_grant",
'''
replacement = '''    "mg_homeserver_operational_grant",
    "mg_homeserver_operational_required_scope",
    "mg_homeserver_operational_require_dataset_scope",
    "required_device_scope",
    "device_scope_allowed",
    "homeserver.reviews.read",
    "homeserver.messages.read",
    "homeserver.crm.read",
    "homeserver.commerce_history.read",
    "homeserver.gifts.read",
    "homeserver.campaigns.read",
'''
if '"mg_homeserver_operational_required_scope"' not in validator:
    if validator.count(marker) != 1:
        raise SystemExit("dataset scope validator anchor was not found")
    validator = validator.replace(marker, replacement, 1)
validator_path.write_text(validator, encoding="utf-8", newline="\n")

print("Dataset exports now require both the owner grant and the matching paired-device scope.")
