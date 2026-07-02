<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/stories/_stories.php';

mg_require_method('GET');
$pdo = mg_db();

try {
    $slug = strtolower(trim((string)($_GET['slug'] ?? '')));
    if ($slug === '' || strlen($slug) > 120 || preg_match('/^[a-z0-9](?:[a-z0-9-]{0,118}[a-z0-9])?$/', $slug) !== 1) {
        mg_fail('Invalid profile.', 422);
    }
    if (!mg_stories_table_exists($pdo, 'microgifter_story_highlights')) {
        mg_ok(['schema_ready' => false, 'highlights' => [], 'permissions' => ['can_manage' => false]]);
        return;
    }
    mg_stories_require_schema($pdo);

    $profileStmt = $pdo->prepare("SELECT pp.user_id,pp.public_id,pp.slug,pp.display_name,pp.visibility,pp.status,u.status user_status FROM public_profiles pp INNER JOIN users u ON u.id=pp.user_id WHERE pp.slug=? LIMIT 1");
    $profileStmt->execute([$slug]);
    $profile = $profileStmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($profile) || (string)$profile['status'] !== 'active' || (string)$profile['user_status'] !== 'active' || !in_array((string)$profile['visibility'], ['public','unlisted'], true)) {
        throw new RuntimeException('Profile not found.');
    }
    $viewer = mg_stories_viewer_user();
    $canManage = is_array($viewer) && ((int)$viewer['id'] === (int)$profile['user_id'] || mg_stories_user_can_admin($viewer));

    $stmt = $pdo->prepare(
        "SELECT h.public_id highlight_id,h.title,h.display_order,h.created_at highlighted_at,
                s.public_id story_id,s.story_type,s.media_type,s.media_url,s.thumbnail_url,s.caption,s.cta_label,s.cta_url,s.created_at story_created_at,s.expires_at,s.status story_status
         FROM microgifter_story_highlights h
         INNER JOIN microgifter_stories s ON s.id=h.story_id
         WHERE h.profile_user_id=? AND h.status='active' AND s.status IN ('active','expired')
         ORDER BY h.display_order ASC,h.created_at DESC,h.id DESC
         LIMIT 48"
    );
    $stmt->execute([(int)$profile['user_id']]);
    $items = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $mediaUrl = mg_stories_safe_url($row['media_url'] ?? '', true);
        $thumbnailUrl = mg_stories_safe_url($row['thumbnail_url'] ?? '', true) ?: $mediaUrl;
        if ($mediaUrl === null) continue;
        $items[] = [
            'id' => (string)$row['highlight_id'],
            'story_id' => (string)$row['story_id'],
            'title' => mg_stories_text($row['title'] ?? '', 120, 'Story Highlight'),
            'caption' => (string)($row['caption'] ?? ''),
            'story_type' => (string)$row['story_type'],
            'media_type' => (string)$row['media_type'],
            'media_url' => $mediaUrl,
            'thumbnail_url' => $thumbnailUrl,
            'cta_label' => (string)($row['cta_label'] ?? ''),
            'cta_url' => mg_stories_safe_url($row['cta_url'] ?? '', true),
            'highlighted_at' => (string)$row['highlighted_at'],
            'created_at' => (string)$row['story_created_at'],
            'expires_at' => (string)$row['expires_at'],
            'story_status' => (string)$row['story_status'],
            'display_order' => (int)$row['display_order'],
        ];
    }

    mg_ok([
        'schema_ready' => true,
        'permissions' => ['can_manage' => $canManage],
        'profile' => [
            'id' => (string)$profile['public_id'],
            'slug' => (string)$profile['slug'],
            'display_name' => (string)$profile['display_name'],
        ],
        'highlights' => $items,
    ]);
} catch (RuntimeException $error) {
    mg_fail($error->getMessage(), 404);
} catch (Throwable $error) {
    mg_security_log('error', 'profile.story_highlights_failed', 'Public profile story highlights failed.', ['exception_class' => $error::class, 'message' => $error->getMessage()]);
    mg_fail('Unable to load profile stories.', 500);
}
