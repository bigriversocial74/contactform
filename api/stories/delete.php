<?php
declare(strict_types=1);

require_once __DIR__ . '/_stories.php';

mg_require_method('POST');
$input = mg_input();
$user = mg_require_api_user();
mg_require_csrf_for_write($input);
$pdo = mg_db();
$userId = (int)$user['id'];
mg_rate_limit('stories.delete', 'user:' . $userId, 60, 60);

try {
    mg_stories_require_schema($pdo);
    $storyPublicId = mg_stories_public_id($input['story_id'] ?? '');
    $stmt = $pdo->prepare("SELECT id,owner_user_id,status FROM microgifter_stories WHERE public_id=? AND status<>'deleted' LIMIT 1");
    $stmt->execute([$storyPublicId]);
    $story = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($story)) throw new RuntimeException('Story is not available.');
    if ((int)$story['owner_user_id'] !== $userId && !mg_stories_user_can_admin($user)) throw new RuntimeException('You cannot delete this story.');
    $pdo->prepare("UPDATE microgifter_stories SET status='deleted',deleted_at=NOW(),updated_at=NOW() WHERE id=?")->execute([(int)$story['id']]);
    mg_audit('stories.deleted', 'microgifter_story', ['story_id' => $storyPublicId], $userId);
    mg_ok(['story_id' => $storyPublicId, 'deleted' => true], 'Story deleted.');
} catch (InvalidArgumentException $error) {
    mg_fail($error->getMessage(), 422);
} catch (RuntimeException $error) {
    mg_fail($error->getMessage(), 403);
} catch (Throwable $error) {
    mg_security_log('error', 'stories.delete_failed', 'Story delete failed.', ['exception_class' => $error::class, 'message' => $error->getMessage()], $userId);
    mg_fail('Unable to delete story.', 500);
}
