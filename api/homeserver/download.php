<?php
declare(strict_types=1);

require_once __DIR__ . '/_homeserver.php';
require_once dirname(__DIR__, 2) . '/includes/homeserver-releases.php';
require_once dirname(__DIR__, 2) . '/includes/homeserver-entitlements.php';

mg_require_method('GET');
$user = mg_require_api_user();
$pdo = mg_db();
mg_homeserver_require_capability(
    $pdo,
    $user,
    'homeserver.download',
    'Downloading HomeServer requires an active paid or complimentary Microgifter package.'
);
if (function_exists('mg_rate_limit')) {
    mg_rate_limit('homeserver.release_download', 'user:' . (int)$user['id'], 30, 600);
}

if (!mg_homeserver_release_schema_ready($pdo)) {
    mg_fail('HomeServer downloads are not configured yet.', 503);
}

$releaseId = strtolower(trim((string)($_GET['release'] ?? '')));
if ($releaseId === '') {
    $release = mg_homeserver_release_latest($pdo);
} else {
    $release = mg_homeserver_release_find_published($pdo, $releaseId);
}
if (!$release) mg_fail('The requested HomeServer release is unavailable.', 404);

try {
    $path = mg_homeserver_release_file_path($release);
} catch (Throwable $error) {
    mg_fail_unexpected($error, 'homeserver.release_file_unavailable', 'The HomeServer installer is unavailable.', 404, [
        'release_id' => (string)$release['public_id'],
        'version' => (string)$release['version'],
    ], (int)$user['id']);
}

try {
    $downloadRequestId = mg_homeserver_release_record_download($pdo, $release, (int)$user['id']);
} catch (Throwable $error) {
    mg_security_log('error', 'homeserver.release_download_tracking_failed', 'HomeServer download tracking failed.', [
        'release_id' => (string)$release['public_id'],
        'version' => (string)$release['version'],
        'exception' => $error->getMessage(),
    ], (int)$user['id']);
    $downloadRequestId = mg_homeserver_release_uuid();
}

mg_audit('homeserver.release_download_started', 'homeserver_release', [
    'release_id' => (string)$release['public_id'],
    'version' => (string)$release['version'],
    'download_request_id' => $downloadRequestId,
], (int)$user['id']);

$filename = mg_homeserver_release_safe_filename((string)$release['original_filename'], (string)$release['version']);
$size = (int)filesize($path);
while (ob_get_level() > 0) ob_end_clean();
header('Content-Type: application/vnd.microsoft.portable-executable');
header('Content-Length: ' . $size);
header('Content-Disposition: attachment; filename="' . addcslashes($filename, '"\\') . '"; filename*=UTF-8\'\'' . rawurlencode($filename));
header('Cache-Control: private, no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('X-Content-Type-Options: nosniff');
header('X-Microgifter-HomeServer-Version: ' . (string)$release['version']);
header('X-Microgifter-Download-ID: ' . $downloadRequestId);
header('Accept-Ranges: none');
readfile($path);
exit;
