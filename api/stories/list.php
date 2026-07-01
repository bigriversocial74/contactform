<?php
declare(strict_types=1);

require_once __DIR__ . '/_stories.php';

mg_require_method('GET');
$pdo = mg_db();
$viewer = mg_stories_viewer_user();
$viewerId = isset($viewer['id']) ? (int)$viewer['id'] : null;
$viewerSession = mg_stories_viewer_session_key();
$identifier = $viewerId !== null ? 'user:' . $viewerId : 'session:' . $viewerSession;
mg_rate_limit('stories.list', $identifier, $viewerId !== null ? 240 : 120, 60);
$limit = max(1, min(80, (int)($_GET['limit'] ?? MG_STORIES_DEFAULT_LIMIT)));

try {
    $schema = mg_stories_schema_status($pdo);
    if (!$schema['ready']) {
        mg_ok(['schema_ready' => false, 'stories' => [], 'missing_tables' => array_keys(array_filter($schema['tables'], static fn($ready) => !$ready))], 'Feed Stories migration is required.');
    }
    mg_ok([
        'schema_ready' => true,
        'stories' => mg_stories_list($pdo, $viewerId, $viewerSession, $limit),
        'viewer' => [
            'authenticated' => $viewerId !== null,
            'merchant' => is_array($viewer) ? mg_stories_user_can_merchant($viewer, $pdo) : false,
        ],
        'limits' => ['expires_hours' => 24, 'max_video_seconds' => MG_STORIES_MAX_VIDEO_SECONDS],
    ], 'Stories loaded.');
} catch (Throwable $error) {
    mg_security_log('warning', 'stories.list_failed', 'Feed Stories list failed.', ['exception_class' => $error::class, 'message' => $error->getMessage()], $viewerId);
    mg_ok(['schema_ready' => false, 'stories' => [], 'empty_reason' => 'stories_unavailable'], 'Stories are unavailable.');
}
