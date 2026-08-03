<?php
declare(strict_types=1);

require_once __DIR__ . '/_homeserver.php';
require_once dirname(__DIR__, 2) . '/includes/homeserver-upgrades.php';

mg_require_method('GET');
mg_homeserver_require_secure_transport();

$pdo = mg_db();
if (!mg_homeserver_release_schema_ready($pdo) || !mg_homeserver_upgrade_schema_ready($pdo)) {
    http_response_code(503);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode(['error' => 'homeserver_upgrade_catalog_unavailable'], JSON_THROW_ON_ERROR);
    exit;
}

try {
    $candidate = mg_homeserver_upgrade_manifest_candidate($pdo);
    if (!$candidate) {
        http_response_code(503);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode(['error' => 'homeserver_update_manifest_unavailable'], JSON_THROW_ON_ERROR);
        exit;
    }
    $manifest = mg_homeserver_upgrade_manifest($candidate, $candidate);
    $json = json_encode(
        $manifest,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    );
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: public, max-age=60, must-revalidate');
    header('ETag: "' . hash('sha256', $json) . '"');
    header('X-Content-Type-Options: nosniff');
    header('Content-Length: ' . strlen($json));
    echo $json;
} catch (Throwable $error) {
    error_log('HomeServer update manifest failure: ' . $error->getMessage());
    http_response_code(503);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode(['error' => 'homeserver_update_manifest_invalid'], JSON_THROW_ON_ERROR);
}
