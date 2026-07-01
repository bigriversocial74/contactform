<?php
declare(strict_types=1);

require_once __DIR__ . '/_stories.php';

mg_require_method('POST');
$input = mg_input();
$pdo = mg_db();
$viewer = mg_stories_viewer_user();
$viewerId = isset($viewer['id']) ? (int)$viewer['id'] : null;
$sessionKey = mg_stories_viewer_session_key();
$identifier = $viewerId !== null ? 'user:' . $viewerId : 'session:' . $sessionKey;
mg_rate_limit('stories.view', $identifier, 240, 60);

try {
    mg_stories_require_schema($pdo);
    $storyPublicId = mg_stories_public_id($input['story_id'] ?? '');
    $duration = isset($input['view_duration_seconds']) ? max(0, min(3600, (int)$input['view_duration_seconds'])) : null;
    $completed = !empty($input['completed']) ? 1 : 0;
    $stmt = $pdo->prepare("SELECT id FROM microgifter_stories WHERE public_id=? AND status='active' AND expires_at>NOW() LIMIT 1");
    $stmt->execute([$storyPublicId]);
    $storyId = (int)($stmt->fetchColumn() ?: 0);
    if ($storyId <= 0) throw new RuntimeException('Story is not available.');

    if ($viewerId !== null) {
        $stmt = $pdo->prepare('INSERT INTO microgifter_story_views (story_id,viewer_user_id,viewer_session_id,view_duration_seconds,completed,viewed_at) VALUES (?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE view_duration_seconds=GREATEST(COALESCE(view_duration_seconds,0),COALESCE(VALUES(view_duration_seconds),0)), completed=GREATEST(completed,VALUES(completed)), viewed_at=NOW()');
        $stmt->execute([$storyId, $viewerId, null, $duration, $completed]);
    } else {
        $stmt = $pdo->prepare('INSERT INTO microgifter_story_views (story_id,viewer_user_id,viewer_session_id,view_duration_seconds,completed,viewed_at) VALUES (?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE view_duration_seconds=GREATEST(COALESCE(view_duration_seconds,0),COALESCE(VALUES(view_duration_seconds),0)), completed=GREATEST(completed,VALUES(completed)), viewed_at=NOW()');
        $stmt->execute([$storyId, null, $sessionKey, $duration, $completed]);
    }
    mg_ok(['story_id' => $storyPublicId, 'viewed' => true], 'Story view recorded.');
} catch (InvalidArgumentException $error) {
    mg_fail($error->getMessage(), 422);
} catch (RuntimeException $error) {
    mg_fail($error->getMessage(), 404);
} catch (Throwable $error) {
    mg_security_log('warning', 'stories.view_failed', 'Story view tracking failed.', ['exception_class' => $error::class, 'message' => $error->getMessage()], $viewerId);
    mg_fail('Unable to record story view.', 500);
}
