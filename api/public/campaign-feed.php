<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/social/_publishing.php';
require_once __DIR__ . '/_campaign_feed_v1.php';

mg_require_method('GET');
$pdo = mg_db();
$viewer = mg_public_profile_session_viewer($pdo);
$viewerId = isset($viewer['id']) ? (int)$viewer['id'] : null;
$mode = strtolower(trim((string)($_GET['mode'] ?? 'discover')));
$limit = mg_publishing_limit($_GET['limit'] ?? MG_CAMPAIGN_FEED_V1_MAX, MG_CAMPAIGN_FEED_V1_MAX, MG_CAMPAIGN_FEED_V1_MAX);
$identifier = $viewerId !== null ? 'user:' . $viewerId : 'ip:' . (mg_client_ip() ?? 'unknown');

mg_rate_limit('campaign.feed.read', $identifier, $viewerId !== null ? 180 : 90, 60);

try {
    $items = mg_campaign_feed_v1_items($pdo, $mode, $viewerId, $limit);
} catch (InvalidArgumentException $error) {
    mg_fail($error->getMessage(), 422);
} catch (RuntimeException $error) {
    mg_fail($error->getMessage(), $mode === 'following' && $viewerId === null ? 401 : 404);
} catch (Throwable $error) {
    mg_security_log('error', 'campaign.feed_failed', 'Campaign feed cards could not be loaded.', [
        'mode' => $mode,
        'exception_class' => $error::class,
        'authenticated' => $viewerId !== null,
    ], $viewerId);
    mg_fail('Unable to load campaign opportunities.', 500);
}

mg_event('campaign.feed_read', [
    'mode' => $mode,
    'result_count' => count($items),
    'campaign_types' => ['watch_video_reward', 'listen_music_reward'],
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
        'campaigns' => ['mode' => $mode, 'items' => $items, 'limit' => $limit],
        'viewer' => ['authenticated' => $viewerId !== null],
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
