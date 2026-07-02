<?php
declare(strict_types=1);

require_once __DIR__ . '/_stories.php';

mg_require_method('POST');
$user = mg_require_api_user();
$input = mg_input();
mg_require_csrf_for_write($input);

$pdo = mg_db();
$userId = (int)$user['id'];

try {
    if (function_exists('mg_rate_limit')) {
        mg_rate_limit('stories.highlight.write', 'user:' . $userId, 60, 60);
    }
    mg_stories_require_schema($pdo);
    if (!mg_stories_table_exists($pdo, 'microgifter_story_highlights')) {
        throw new RuntimeException('Story highlights setup is incomplete. Run database/microgifter_story_highlights.sql on the active database.');
    }

    $storyPublicId = mg_stories_public_id($input['story_id'] ?? '');
    $action = strtolower(trim((string)($input['action'] ?? 'save')));
    if (!in_array($action, ['save', 'remove'], true)) {
        throw new InvalidArgumentException('Invalid highlight action.');
    }
    $title = mg_stories_text($input['title'] ?? '', 120, '');

    $stmt = $pdo->prepare("SELECT id,public_id,owner_user_id,merchant_user_id,caption,status,created_at,expires_at FROM microgifter_stories WHERE public_id=? AND status IN ('active','expired') LIMIT 1");
    $stmt->execute([$storyPublicId]);
    $story = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($story)) {
        throw new RuntimeException('Story is not available.');
    }
    $isOwner = (int)$story['owner_user_id'] === $userId;
    if (!$isOwner && !mg_stories_user_can_admin($user)) {
        mg_fail('Only the story owner can manage highlights.', 403);
    }
    $profileUserId = (int)$story['owner_user_id'];
    $storyId = (int)$story['id'];

    if ($action === 'remove') {
        $pdo->prepare("UPDATE microgifter_story_highlights SET status='deleted',deleted_at=NOW(),updated_at=NOW() WHERE profile_user_id=? AND story_id=? AND status='active'")
            ->execute([$profileUserId, $storyId]);
        mg_ok(['story_id' => $storyPublicId, 'highlighted' => false], 'Story removed from highlights.');
        return;
    }

    if ($title === '') {
        $caption = trim((string)($story['caption'] ?? ''));
        $title = $caption !== '' ? mg_stories_text($caption, 120, 'Story Highlight') : 'Story Highlight';
    }
    $publicId = mg_stories_uuid();
    $orderStmt = $pdo->prepare("SELECT COALESCE(MAX(display_order),0)+10 FROM microgifter_story_highlights WHERE profile_user_id=?");
    $orderStmt->execute([$profileUserId]);
    $displayOrder = (int)($orderStmt->fetchColumn() ?: 10);

    $stmt = $pdo->prepare(
        "INSERT INTO microgifter_story_highlights
         (public_id,profile_user_id,owner_user_id,story_id,title,display_order,status,created_at,updated_at,deleted_at)
         VALUES (?,?,?,?,?,?,'active',NOW(),NOW(),NULL)
         ON DUPLICATE KEY UPDATE title=VALUES(title),status='active',deleted_at=NULL,updated_at=NOW()"
    );
    $stmt->execute([$publicId, $profileUserId, $userId, $storyId, $title, $displayOrder]);

    mg_audit('stories.highlight_saved', 'story', ['story_id' => $storyPublicId], $userId);
    if (function_exists('mg_event')) {
        mg_event('stories.highlight_saved', ['story_id' => $storyPublicId, 'profile_user_id' => $profileUserId], $userId);
    }
    mg_ok(['story_id' => $storyPublicId, 'highlighted' => true, 'title' => $title], 'Story saved to highlights.');
} catch (InvalidArgumentException $error) {
    mg_fail($error->getMessage(), 422);
} catch (RuntimeException $error) {
    mg_fail($error->getMessage(), 404);
} catch (Throwable $error) {
    mg_security_log('error', 'stories.highlight_failed', 'Story highlight update failed.', ['exception_class' => $error::class, 'message' => $error->getMessage()], $userId);
    mg_fail('Unable to update story highlight.', 500);
}
