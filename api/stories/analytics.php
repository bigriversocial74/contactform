<?php
declare(strict_types=1);

require_once __DIR__ . '/_stories.php';

mg_require_method('GET');
$user = mg_require_api_user();
$pdo = mg_db();
$userId = (int)$user['id'];

try {
    if (function_exists('mg_rate_limit')) {
        mg_rate_limit('stories.analytics', 'user:' . $userId, 120, 60);
    }
    mg_stories_require_schema($pdo);
    $storyPublicId = mg_stories_public_id($_GET['story_id'] ?? '');
    $stmt = $pdo->prepare('SELECT id,public_id,owner_user_id,merchant_user_id,story_type,media_type,caption,created_at,expires_at,status FROM microgifter_stories WHERE public_id=? LIMIT 1');
    $stmt->execute([$storyPublicId]);
    $story = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($story)) {
        throw new RuntimeException('Story is not available.');
    }
    $isOwner = (int)$story['owner_user_id'] === $userId;
    if (!$isOwner && !mg_stories_user_can_admin($user)) {
        mg_fail('Story analytics are only available to the story owner.', 403);
    }

    $storyId = (int)$story['id'];
    $statsStmt = $pdo->prepare(
        "SELECT COUNT(*) total_views,
                COUNT(DISTINCT viewer_user_id) signed_in_viewers,
                SUM(CASE WHEN viewer_user_id IS NULL THEN 1 ELSE 0 END) anonymous_viewers,
                SUM(CASE WHEN completed=1 THEN 1 ELSE 0 END) completed_views,
                AVG(CASE WHEN view_duration_seconds IS NULL THEN NULL ELSE view_duration_seconds END) avg_duration_seconds,
                MAX(viewed_at) last_viewed_at
         FROM microgifter_story_views
         WHERE story_id=?"
    );
    $statsStmt->execute([$storyId]);
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $viewerStmt = $pdo->prepare(
        "SELECT v.viewer_user_id,v.viewer_session_id,v.viewed_at,v.view_duration_seconds,v.completed,
                COALESCE(NULLIF(pp.display_name,''),NULLIF(u.display_name,''),NULLIF(u.full_name,''),u.email) display_name,
                pp.public_id profile_public_id,pp.slug,pp.avatar_url,pp.profile_type
         FROM microgifter_story_views v
         LEFT JOIN users u ON u.id=v.viewer_user_id
         LEFT JOIN public_profiles pp ON pp.user_id=v.viewer_user_id AND pp.status='active' AND pp.visibility IN ('public','unlisted')
         WHERE v.story_id=?
         ORDER BY v.viewed_at DESC,v.id DESC
         LIMIT 80"
    );
    $viewerStmt->execute([$storyId]);
    $viewers = [];
    foreach ($viewerStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $viewerUserId = isset($row['viewer_user_id']) ? (int)$row['viewer_user_id'] : 0;
        $slug = trim((string)($row['slug'] ?? ''));
        $profilePublicId = trim((string)($row['profile_public_id'] ?? ''));
        $viewers[] = [
            'type' => $viewerUserId > 0 ? 'user' : 'anonymous',
            'user_id' => $viewerUserId > 0 ? $viewerUserId : null,
            'name' => $viewerUserId > 0 ? mg_stories_text($row['display_name'] ?? '', 140, 'Microgifter member') : 'Anonymous viewer',
            'profile_id' => $profilePublicId !== '' ? $profilePublicId : null,
            'profile_type' => mg_stories_text($row['profile_type'] ?? 'profile', 40, 'profile'),
            'profile_url' => $slug !== '' ? '/profile.php?slug=' . rawurlencode($slug) : null,
            'avatar_url' => mg_stories_safe_url($row['avatar_url'] ?? null, true),
            'viewed_at' => (string)($row['viewed_at'] ?? ''),
            'duration_seconds' => $row['view_duration_seconds'] !== null ? (int)$row['view_duration_seconds'] : null,
            'completed' => !empty($row['completed']),
        ];
    }

    $totalViews = (int)($stats['total_views'] ?? 0);
    $completedViews = (int)($stats['completed_views'] ?? 0);
    $signedInViewers = (int)($stats['signed_in_viewers'] ?? 0);
    $anonymousViewers = (int)($stats['anonymous_viewers'] ?? 0);
    mg_ok([
        'story' => [
            'id' => (string)$story['public_id'],
            'story_type' => (string)$story['story_type'],
            'media_type' => (string)$story['media_type'],
            'caption' => (string)($story['caption'] ?? ''),
            'created_at' => (string)$story['created_at'],
            'expires_at' => (string)$story['expires_at'],
            'status' => (string)$story['status'],
        ],
        'summary' => [
            'total_views' => $totalViews,
            'unique_viewers' => $signedInViewers + $anonymousViewers,
            'signed_in_viewers' => $signedInViewers,
            'anonymous_viewers' => $anonymousViewers,
            'completed_views' => $completedViews,
            'completion_rate' => $totalViews > 0 ? round(($completedViews / $totalViews) * 100, 1) : 0,
            'avg_duration_seconds' => $stats['avg_duration_seconds'] !== null ? round((float)$stats['avg_duration_seconds'], 1) : null,
            'last_viewed_at' => $stats['last_viewed_at'] !== null ? (string)$stats['last_viewed_at'] : null,
        ],
        'viewers' => $viewers,
    ]);
} catch (InvalidArgumentException $error) {
    mg_fail($error->getMessage(), 422);
} catch (RuntimeException $error) {
    mg_fail($error->getMessage(), 404);
} catch (Throwable $error) {
    mg_security_log('error', 'stories.analytics_failed', 'Story analytics failed.', ['exception_class' => $error::class, 'message' => $error->getMessage()], $userId);
    mg_fail('Unable to load story analytics.', 500);
}
