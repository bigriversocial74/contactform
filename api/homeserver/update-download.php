<?php
declare(strict_types=1);

require_once __DIR__ . '/_homeserver.php';
require_once dirname(__DIR__, 2) . '/includes/homeserver-upgrades.php';

mg_require_method('GET');
mg_homeserver_require_secure_transport();

$publicId = strtolower(trim((string)($_GET['release'] ?? '')));
if (preg_match('/^[a-f0-9-]{36}$/', $publicId) !== 1) {
    http_response_code(404);
    exit;
}

$pdo = mg_db();
if (!mg_homeserver_release_schema_ready($pdo) || !mg_homeserver_upgrade_schema_ready($pdo)) {
    http_response_code(503);
    exit;
}

try {
    $bundle = mg_homeserver_upgrade_release_bundle($pdo, $publicId);
    if (!$bundle
        || (string)$bundle['status'] !== 'published'
        || (string)($bundle['control_state'] ?? '') !== 'active'
        || !empty($bundle['revoked_at'])) {
        http_response_code(404);
        exit;
    }

    // Revalidate the exact signed payload before releasing installer bytes.
    mg_homeserver_upgrade_manifest($bundle, $bundle);
    $path = mg_homeserver_release_file_path($bundle);
    $actualHash = hash_file('sha256', $path);
    if (!is_string($actualHash) || !hash_equals(strtolower((string)$bundle['checksum_sha256']), strtolower($actualHash))) {
        throw new RuntimeException('The HomeServer installer checksum no longer matches its release record.');
    }

    $downloadId = mg_homeserver_release_record_download($pdo, $bundle, 0);
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="Microgifter-HomeServer-Setup.exe"');
    header('Content-Length: ' . (int)$bundle['byte_size']);
    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');
    header('X-Microgifter-Release-ID: ' . $publicId);
    header('X-Microgifter-Download-ID: ' . $downloadId);
    readfile($path);
} catch (Throwable $error) {
    error_log('HomeServer update download failure: ' . $error->getMessage());
    if (!headers_sent()) http_response_code(503);
}
