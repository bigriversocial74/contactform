<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found.');
}

$root = dirname(__DIR__);
$failures = [];
$check = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};
$read = static function (string $relative) use ($root): string {
    $path = $root . '/' . $relative;
    $content = file_get_contents($path);
    if (!is_string($content) || $content === '') throw new RuntimeException('Unable to read ' . $relative);
    return $content;
};

$composer = json_decode($read('composer.json'), true);
$check(is_array($composer), 'composer.json must decode as an object.');
$check(isset($composer['require']['ext-sodium']), 'The HomeServer cloud contract must declare ext-sodium.');

$manifest = require $root . '/config/migrations.php';
$migrationName = '20260724_homeserver_cloud_pairing_sync_v1.sql';
$check(in_array($migrationName, $manifest['ordered_files'] ?? [], true), 'HomeServer migration is not registered in the canonical manifest.');

$migration = $read('database/' . $migrationName);
foreach (['homeserver_devices', 'homeserver_pairing_codes', 'homeserver_request_nonces', 'homeserver_sync_receipts'] as $table) {
    $check(str_contains($migration, 'CREATE TABLE IF NOT EXISTS ' . $table), 'Missing HomeServer table: ' . $table);
}
$check(str_contains($migration, 'UNIQUE KEY uq_homeserver_devices_installation'), 'Installation identity must be unique.');
$check(str_contains($migration, 'UNIQUE KEY uq_homeserver_sync_receipts_idempotency'), 'Cloud receipts must enforce idempotency.');
$check(str_contains($migration, 'token_hash CHAR(64)'), 'Device tokens must be stored only as hashes.');
$check(!str_contains($migration, 'device_token TEXT'), 'Raw device tokens cannot be stored in the cloud database.');

$foundation = $read('api/homeserver/_homeserver.php');
foreach ([
    'sodium_crypto_sign_verify_detached',
    'MG_HOMESERVER_SIGNATURE_WINDOW_SECONDS',
    'MG_HOMESERVER_MAX_BODY_BYTES',
    'homeserver_request_nonces',
    'REDIRECT_HTTP_AUTHORIZATION',
    'cloud_authority_required',
] as $needle) {
    $check(str_contains($foundation, $needle), 'HomeServer security foundation is missing: ' . $needle);
}
foreach (['commerce.', 'payment.', 'claim.', 'redemption.', 'ownership.'] as $authorityPrefix) {
    $check(str_contains($foundation, "str_starts_with(\$operationType, '" . $authorityPrefix . "')"), 'Cloud authority rejection is missing for ' . $authorityPrefix);
}

$sync = $read('api/homeserver/sync.php');
$validationPosition = strpos($sync, '$validated = [];');
$transactionPosition = strpos($sync, '$pdo->beginTransaction();');
$check($validationPosition !== false && $transactionPosition !== false && $validationPosition < $transactionPosition, 'Sync validation must finish before the transaction starts.');
$check(str_contains($sync, 'FOR UPDATE'), 'Sync receipt replays must be transactionally locked.');
$check(str_contains($sync, 'hash_equals'), 'Sync idempotency request hashes must use constant-time comparison.');

$pairing = $read('api/homeserver/pair.php');
$check(str_contains($pairing, "function_exists('sodium_crypto_sign_verify_detached')"), 'Pairing must fail closed when sodium is unavailable.');
$check(str_contains($pairing, "status='active'"), 'Owner-approved re-pairing must rotate and reactivate scoped credentials.');
$check(str_contains($pairing, 'consumed_at=UTC_TIMESTAMP()'), 'Pairing codes must be consumed exactly once in UTC.');

$revocation = $read('api/homeserver/revoke.php');
$check(str_contains($revocation, "status='revoked'"), 'Device revocation must persist a terminal cloud status.');
$check(str_contains($revocation, 'revoked_at=UTC_TIMESTAMP()'), 'Device revocation must be recorded in UTC.');
$check(str_contains($revocation, 'token_hash=?'), 'Device revocation must invalidate the stored token hash.');

$requiredEndpoints = [
    'api/homeserver/pairing-code.php',
    'api/homeserver/pair.php',
    'api/homeserver/status.php',
    'api/homeserver/sync.php',
    'api/homeserver/devices.php',
    'api/homeserver/revoke.php',
];
foreach ($requiredEndpoints as $endpoint) {
    $check(is_file($root . '/' . $endpoint), 'Missing HomeServer endpoint: ' . $endpoint);
}

$requiredAccountFiles = [
    'account-homeserver.php',
    'includes/account/homeserver-view.php',
    'assets/js/homeserver-account.js',
    'assets/css/homeserver-account.css',
];
foreach ($requiredAccountFiles as $accountFile) {
    $check(is_file($root . '/' . $accountFile), 'Missing HomeServer account control: ' . $accountFile);
}
$accountEntry = $read('account-homeserver.php');
$check(str_contains($accountEntry, "MG_ACCOUNT_VIEW', 'homeserver'"), 'HomeServer account entrypoint is not routed through the canonical account shell.');
$accountShell = $read('account.php');
$check(str_contains($accountShell, "\$accountView === 'homeserver'"), 'Canonical account shell does not render the HomeServer workspace.');
$accountScript = $read('assets/js/homeserver-account.js');
foreach (['/api/homeserver/pairing-code.php', '/api/homeserver/devices.php', '/api/homeserver/revoke.php'] as $accountEndpoint) {
    $check(str_contains($accountScript, $accountEndpoint), 'HomeServer account controls are missing endpoint: ' . $accountEndpoint);
}
$check(str_contains($accountScript, 'navigator.clipboard.writeText'), 'HomeServer pairing code copy control is missing.');
$check(str_contains($accountScript, 'window.confirm'), 'HomeServer revocation requires an explicit owner confirmation.');

$body = '{"x":1}';
$bodyHash = hash('sha256', $body);
$check($bodyHash === '5041bf1f713df204784353e82f6a4a535931cb64f1f4b4a5aeaffcb720918b22', 'Canonical body hash does not match the HomeServer Rust client vector.');
$canonical = "POST\n/api/homeserver/sync.php\n100\nnonce-value-1234\n" . $bodyHash;
$check(substr_count($canonical, "\n") === 4, 'Canonical request must contain five ordered fields.');

if (!function_exists('sodium_crypto_sign_keypair')) {
    $failures[] = 'The sodium extension is required for HomeServer request verification.';
} else {
    $keypair = sodium_crypto_sign_keypair();
    $secret = sodium_crypto_sign_secretkey($keypair);
    $public = sodium_crypto_sign_publickey($keypair);
    $signature = sodium_crypto_sign_detached($canonical, $secret);
    $check(sodium_crypto_sign_verify_detached($signature, $canonical, $public), 'Canonical Ed25519 signature did not verify.');
    $check(!sodium_crypto_sign_verify_detached($signature, $canonical . 'tampered', $public), 'Tampered HomeServer request unexpectedly verified.');
    sodium_memzero($secret);
}

if ($failures !== []) {
    fwrite(STDERR, "HomeServer protocol validation failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "HomeServer pairing and synchronization protocol valid.\n";
