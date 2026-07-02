<?php
declare(strict_types=1);

require_once __DIR__ . '/_stories.php';

mg_require_method('POST');
$user = mg_require_api_user();
$input = mg_input();
mg_require_csrf_for_write($input);

$pdo = mg_db();
$userId = (int)$user['id'];

function mg_story_highlight_id(mixed $value): string
{
    return mg_stories_public_id($value);
}

function mg_story_highlight_require_schema(PDO $pdo): void
{
    mg_stories_require_schema($pdo);
    if (!mg_stories_table_exists($pdo, 'microgifter_story_highlights')) {
        throw new RuntimeException('Story highlights setup is incomplete. Run database/microgifter_story_highlights.sql on the active database.');
    }
}

function mg_story_highlight_load(PDO $pdo, string $highlightPublicId): array
{
    $stmt = $pdo->prepare(
        "SELECT h.*,s.public_id story_public_id,s.owner_user_id story_owner_user_id,s.caption story_caption
         FROM microgifter_story_highlights h
         INNER JOIN microgifter_stories s ON s.id=h.story_id
         WHERE h.public_id=? AND h.status='active'
         LIMIT 1"
    );
    $stmt->execute([$highlightPublicId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) throw new RuntimeException('Highlight is not available.');
    return $row;
}

function mg_story_highlight_assert_owner(array $highlight, array $user, int $userId): void
{
    $allowed = (int)($highlight['profile_user_id'] ?? 0) === $userId
        || (int)($highlight['owner_user_id'] ?? 0) === $userId
        || (int)($highlight['story_owner_user_id'] ?? 0) === $userId
        || mg_stories_user_can_admin($user);
    if (!$allowed) mg_fail('Only the profile owner can manage highlights.', 403);
}

function mg_story_highlight_project(array $row): array
{
    return [
        'id' => (string)$row['public_id'],
        'story_id' => (string)($row['story_public_id'] ?? ''),
        'title' => mg_stories_text($row['title'] ?? '', 120, 'Story Highlight'),
        'display_order' => (int)($row['display_order'] ?? 0),
        'status' => (string)($row['status'] ?? 'active'),
    ];
}

try {
    if (function_exists('mg_rate_limit')) {
        mg_rate_limit('stories.highlight.write', 'user:' . $userId, 90, 60);
    }
    mg_story_highlight_require_schema($pdo);

    $action = strtolower(trim((string)($input['action'] ?? 'save')));
    if (!in_array($action, ['save', 'remove', 'rename', 'reorder'], true)) {
        throw new InvalidArgumentException('Invalid highlight action.');
    }
    $title = mg_stories_text($input['title'] ?? '', 120, '');

    if (in_array($action, ['remove', 'rename', 'reorder'], true) && trim((string)($input['highlight_id'] ?? '')) !== '') {
        $highlightPublicId = mg_story_highlight_id($input['highlight_id']);
        $highlight = mg_story_highlight_load($pdo, $highlightPublicId);
        mg_story_highlight_assert_owner($highlight, $user, $userId);

        if ($action === 'remove') {
            $pdo->prepare("UPDATE microgifter_story_highlights SET status='deleted',deleted_at=NOW(),updated_at=NOW() WHERE id=? AND status='active'")
                ->execute([(int)$highlight['id']]);
            mg_audit('stories.highlight_removed', 'story_highlight', ['highlight_id' => $highlightPublicId], $userId);
            mg_ok(['highlight_id' => $highlightPublicId, 'highlighted' => false], 'Highlight removed.');
            return;
        }

        if ($action === 'rename') {
            if ($title === '') throw new InvalidArgumentException('Highlight title is required.');
            $pdo->prepare('UPDATE microgifter_story_highlights SET title=?,updated_at=NOW() WHERE id=? AND status=\'active\'')
                ->execute([$title, (int)$highlight['id']]);
            $highlight['title'] = $title;
            mg_audit('stories.highlight_renamed', 'story_highlight', ['highlight_id' => $highlightPublicId], $userId);
            mg_ok(['highlight' => mg_story_highlight_project($highlight)], 'Highlight renamed.');
            return;
        }

        $direction = strtolower(trim((string)($input['direction'] ?? '')));
        if (!in_array($direction, ['up', 'down'], true)) throw new InvalidArgumentException('Invalid reorder direction.');
        $operator = $direction === 'up' ? '<' : '>';
        $order = $direction === 'up' ? 'DESC' : 'ASC';
        $neighborStmt = $pdo->prepare(
            "SELECT * FROM microgifter_story_highlights
             WHERE profile_user_id=? AND status='active'
               AND (display_order {$operator} ? OR (display_order=? AND id {$operator} ?))
             ORDER BY display_order {$order}, id {$order}
             LIMIT 1"
        );
        $currentOrder = (int)$highlight['display_order'];
        $neighborStmt->execute([(int)$highlight['profile_user_id'], $currentOrder, $currentOrder, (int)$highlight['id']]);
        $neighbor = $neighborStmt->fetch(PDO::FETCH_ASSOC);
        if (is_array($neighbor)) {
            $pdo->beginTransaction();
            $pdo->prepare('UPDATE microgifter_story_highlights SET display_order=?,updated_at=NOW() WHERE id=?')->execute([(int)$neighbor['display_order'], (int)$highlight['id']]);
            $pdo->prepare('UPDATE microgifter_story_highlights SET display_order=?,updated_at=NOW() WHERE id=?')->execute([$currentOrder, (int)$neighbor['id']]);
            $pdo->commit();
        }
        mg_audit('stories.highlight_reordered', 'story_highlight', ['highlight_id' => $highlightPublicId, 'direction' => $direction], $userId);
        mg_ok(['highlight_id' => $highlightPublicId, 'direction' => $direction], 'Highlight order updated.');
        return;
    }

    $storyPublicId = mg_stories_public_id($input['story_id'] ?? '');
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
        mg_audit('stories.highlight_removed', 'story', ['story_id' => $storyPublicId], $userId);
        mg_ok(['story_id' => $storyPublicId, 'highlighted' => false], 'Story removed from highlights.');
        return;
    }

    if ($action !== 'save') {
        throw new InvalidArgumentException('Highlight identifier is required for this action.');
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
    $lookup = $pdo->prepare("SELECT h.*,s.public_id story_public_id FROM microgifter_story_highlights h INNER JOIN microgifter_stories s ON s.id=h.story_id WHERE h.profile_user_id=? AND h.story_id=? AND h.status='active' LIMIT 1");
    $lookup->execute([$profileUserId, $storyId]);
    $highlight = $lookup->fetch(PDO::FETCH_ASSOC) ?: ['public_id' => $publicId, 'story_public_id' => $storyPublicId, 'title' => $title, 'display_order' => $displayOrder, 'status' => 'active'];

    mg_audit('stories.highlight_saved', 'story', ['story_id' => $storyPublicId], $userId);
    if (function_exists('mg_event')) {
        mg_event('stories.highlight_saved', ['story_id' => $storyPublicId, 'profile_user_id' => $profileUserId], $userId);
    }
    mg_ok(['story_id' => $storyPublicId, 'highlighted' => true, 'highlight' => mg_story_highlight_project($highlight)], 'Story saved to highlights.');
} catch (InvalidArgumentException $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_fail($error->getMessage(), 422);
} catch (RuntimeException $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_fail($error->getMessage(), 404);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('error', 'stories.highlight_failed', 'Story highlight update failed.', ['exception_class' => $error::class, 'message' => $error->getMessage()], $userId);
    mg_fail('Unable to update story highlight.', 500);
}
