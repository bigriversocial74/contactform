<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/homeserver-upgrades.php';

$user = mg_require_permission('admin.settings.manage');
mg_require_method('POST');
$input = mg_input();
mg_require_csrf_for_write($input);
if (function_exists('mg_rate_limit')) {
    mg_rate_limit('admin.homeserver_upgrade_signing_payload', 'user:' . (int)$user['id'], 120, 3600);
}

$pdo = mg_db();
if (!mg_homeserver_release_schema_ready($pdo) || !mg_homeserver_upgrade_schema_ready($pdo)) {
    mg_fail('Run the HomeServer release and upgrade-control migrations first.', 409);
}

$release = mg_homeserver_upgrade_release_bundle($pdo, (string)($input['release_id'] ?? ''));
if (!$release) mg_fail('HomeServer release not found.', 404);
if ((string)$release['status'] === 'retired') mg_fail('A retired release cannot be signed.', 409);

$thumbprint = strtoupper(preg_replace('/\s+/', '', trim((string)($input['authenticode_thumbprint'] ?? ''))) ?? '');
if (preg_match('/^(?:[A-F0-9]{40}|[A-F0-9]{64})$/', $thumbprint) !== 1) {
    mg_fail('Enter the exact Authenticode certificate thumbprint.', 422);
}

$control = array_merge($release, [
    'manifest_schema_version' => MG_HOMESERVER_UPGRADE_MANIFEST_SCHEMA_VERSION,
    'manifest_key_id' => trim((string)($input['manifest_key_id'] ?? MG_HOMESERVER_UPGRADE_DEFAULT_KEY_ID)),
    'authenticode_thumbprint' => $thumbprint,
]);
$payload = mg_homeserver_upgrade_manifest_payload($release, $control);
$canonical = mg_homeserver_upgrade_canonical_payload_json($release, $control);

mg_ok([
    'release_id' => (string)$release['public_id'],
    'version' => (string)$release['version'],
    'key_id' => (string)$control['manifest_key_id'],
    'payload' => $payload,
    'canonical_payload_json' => $canonical,
    'payload_sha256' => hash('sha256', $canonical),
    'signature_encoding' => 'Ed25519 base64url without padding',
], 'Signing payload generated.');
