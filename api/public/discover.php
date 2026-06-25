<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/profiles/_product_discovery.php';
require_once dirname(__DIR__) . '/profiles/_discovery_market_metrics.php';

mg_require_method('GET');
$pdo = mg_db();
$viewer = mg_profile_discovery_viewer($pdo);
$viewerId = isset($viewer['id']) ? (int)$viewer['id'] : null;

$identifier = $viewerId !== null ? 'user:' . $viewerId : 'ip:' . (mg_client_ip() ?? 'unknown');
mg_rate_limit('profile.discovery.read', $identifier, $viewerId !== null ? 240 : 90, 60);

try {
    $data = mg_profile_discovery_read($pdo, $_GET, $viewerId);
    $data = mg_profile_discovery_enrich_market_metrics($pdo, $data);
    $data['products'] = mg_product_discovery_search($pdo, $_GET, $viewerId);
} catch (InvalidArgumentException $error) {
    mg_security_log('warning', 'profile.discovery.invalid_request', 'Invalid profile discovery request.', [
        'reason' => $error->getMessage(),
        'authenticated' => $viewerId !== null,
    ], $viewerId);
    mg_fail($error->getMessage() === 'Invalid pagination cursor.' ? 'Invalid pagination cursor.' : 'Invalid search filters.', 422);
} catch (Throwable $error) {
    mg_security_log('error', 'profile.discovery.failed', 'Profile discovery query failed.', [
        'exception_class' => $error::class,
        'authenticated' => $viewerId !== null,
    ], $viewerId);
    mg_fail('Unable to search profiles and local vouchers.', 500);
}

mg_event('profile.discovery.read', [
    'authenticated' => $viewerId !== null,
    'query_present' => trim((string)($_GET['q'] ?? '')) !== '',
    'profile_result_count' => count($data['results']['items'] ?? []),
    'product_result_count' => count($data['products']['items'] ?? []),
], $viewerId);

if ($viewerId === null) {
    header_remove('Set-Cookie');
    header('Cache-Control: public, max-age=30, stale-while-revalidate=30');
} else {
    header('Cache-Control: private, no-store, max-age=0');
}
header('Vary: Cookie, Authorization');
header('X-Robots-Tag: noindex, follow');
http_response_code(200);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => true, 'message' => 'OK', 'data' => $data], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
