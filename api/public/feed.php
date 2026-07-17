<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/social/_publishing.php';
require_once __DIR__ . '/_feed_contract_v2.php';

mg_require_method('GET');
$pdo = mg_db();
$viewer = mg_public_profile_session_viewer($pdo);
$viewerId = isset($viewer['id']) ? (int)$viewer['id'] : null;
$mode = strtolower(trim((string)($_GET['mode'] ?? 'discover')));
$cursor = isset($_GET['cursor']) ? (string)$_GET['cursor'] : null;
$limit = $_GET['limit'] ?? MG_SOCIAL_FEED_DEFAULT_LIMIT;
$identifier = $viewerId !== null ? 'user:' . $viewerId : 'ip:' . (mg_client_ip() ?? 'unknown');

mg_rate_limit('social.feed.read', $identifier, $viewerId !== null ? 240 : 120, 60);

try {
    $contract = mg_public_feed_contract_v2($pdo, $mode, $viewerId, $cursor, (int)$limit);
} catch (InvalidArgumentException $error) {
    mg_security_log('warning', 'social.feed_invalid', 'Invalid social feed request.', [
        'mode' => $mode,
        'reason' => $error->getMessage(),
        'authenticated' => $viewerId !== null,
    ], $viewerId);
    mg_fail($error->getMessage(), 422);
} catch (RuntimeException $error) {
    mg_fail($error->getMessage(), $mode === 'following' && $viewerId === null ? 401 : 404);
} catch (Throwable $error) {
    mg_security_log('error', 'social.feed_contract_failed', 'Feed Contract v2 initialization failed.', [
        'mode' => $mode,
        'exception_class' => $error::class,
        'authenticated' => $viewerId !== null,
    ], $viewerId);
    mg_fail('Unable to load the feed.', 500);
}

$feed = is_array($contract['feed'] ?? null) ? $contract['feed'] : [];
$campaigns = is_array($contract['campaigns'] ?? null) ? $contract['campaigns'] : [];
$sources = is_array($contract['sources'] ?? null) ? $contract['sources'] : [];

mg_event('social.feed_read', [
    'contract_version' => (int)($contract['contract_version'] ?? 2),
    'mode' => $mode,
    'result_count' => count($feed['items'] ?? []),
    'campaign_count' => count($campaigns['items'] ?? []),
    'posts_source_ok' => !empty($sources['posts']['ok']),
    'campaigns_source_ok' => !empty($sources['campaigns']['ok']),
    'authenticated' => $viewerId !== null,
], $viewerId);

if ($viewerId === null && $mode === 'discover') {
    header_remove('Set-Cookie');
    header('Cache-Control: public, max-age=20, stale-while-revalidate=20');
} else {
    header('Cache-Control: private, no-store, max-age=0');
}
header('Vary: Cookie, Authorization');
header('X-Robots-Tag: noindex, follow');
http_response_code(200);
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'ok' => true,
    'message' => 'OK',
    'data' => [
        'contract_version' => (int)($contract['contract_version'] ?? 2),
        'mode' => $mode,
        'feed' => $feed,
        'campaigns' => $campaigns,
        'sources' => $sources,
        'warnings' => array_values((array)($contract['warnings'] ?? [])),
        'viewer' => ['authenticated' => $viewerId !== null],
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
