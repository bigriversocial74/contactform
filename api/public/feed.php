<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/social/_publishing.php';

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
    $feed = mg_publishing_feed($pdo, $mode, $viewerId, $cursor, (int)$limit);
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
    mg_security_log('error', 'social.feed_failed', 'Social feed read failed.', [
        'mode' => $mode,
        'exception_class' => $error::class,
        'authenticated' => $viewerId !== null,
    ], $viewerId);
    mg_fail('Unable to load the feed.', 500);
}

mg_event('social.feed_read', [
    'mode' => $mode,
    'result_count' => count($feed['items'] ?? []),
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
echo json_encode(['ok'=>true,'message'=>'OK','data'=>['feed'=>$feed,'viewer'=>['authenticated'=>$viewerId!==null]]], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
