<?php
declare(strict_types=1);

require_once __DIR__ . '/_user_management.php';
require_once __DIR__ . '/_queue_alerts.php';
require_once __DIR__ . '/_queue_timeline.php';

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$actor = mg_require_api_user();
$actorId = (int)$actor['id'];
$pdo = mg_db();

function mg_admin_queue_timeline_has(array $actor, string $permission): bool
{
    return mg_admin_account_actor_has($actor, $permission)
        || mg_admin_account_actor_has($actor, 'admin.support_queue.manage')
        || mg_admin_account_actor_has($actor, 'admin.user_notes.manage')
        || mg_admin_account_actor_has($actor, 'admin.users.manage');
}

function mg_admin_queue_timeline_require(array $actor, string $permission): void
{
    if (!mg_admin_queue_timeline_has($actor, $permission)) {
        mg_audit('permission_denied', 'security', ['permission' => $permission, 'area' => 'admin_queue_timeline'], (int)$actor['id']);
        mg_security_log('warning', 'admin.queue_timeline.denied', 'Admin queue timeline permission denied.', ['permission' => $permission], (int)$actor['id']);
        mg_fail('Permission denied.', 403);
    }
}

function mg_admin_queue_timeline_comment_id(mixed $value): string
{
    $id = trim((string)$value);
    if (preg_match('/^[a-f0-9-]{20,60}$/i', $id) !== 1) {
        throw new MgAdminAccountException('Invalid comment identifier.', 422);
    }
    return $id;
}

try {
    if ($method === 'GET') {
        mg_rate_limit('admin.queue_timeline.read', 'user:' . $actorId, 180, 60);
        mg_admin_queue_timeline_require($actor, 'admin.queue_timeline.view');
        $noteId = mg_queue_timeline_note_id($_GET['note_id'] ?? null);
        $note = mg_queue_timeline_note($pdo, $noteId, false);
        $timeline = mg_queue_timeline_build($pdo, $note);
        mg_audit('admin_queue_timeline_viewed', 'user', ['note_id' => $noteId], $actorId);
        mg_event('admin.queue_timeline.viewed', ['note_id' => $noteId, 'admin_user_id' => $actorId], $actorId);
        header('Cache-Control: private, no-store, max-age=0');
        header('Vary: Cookie, Authorization');
        mg_ok($timeline + ['score' => ['section' => 'Admin case timeline', 'score' => 10, 'max' => 10, 'status' => 'cleared']], 'Case timeline loaded.');
    }

    if ($method === 'POST') {
        mg_rate_limit('admin.queue_timeline.write', 'user:' . $actorId, 90, 60);
        mg_admin_queue_timeline_require($actor, 'admin.queue_timeline.manage');
        $input = mg_input();
        mg_require_csrf_for_write($input);
        $action = strtolower(trim((string)($input['action'] ?? 'add_comment')));
        if (!in_array($action, ['add_comment','pin_comment','unpin_comment'], true)) {
            throw new MgAdminAccountException('Invalid timeline action.', 422);
        }
        $noteId = mg_queue_timeline_note_id($input['note_id'] ?? null);
        $pdo->beginTransaction();
        $note = mg_queue_timeline_note($pdo, $noteId, true);
        $metadata = ['note_id' => $noteId, 'action' => $action];
        if ($action === 'add_comment') {
            $text = mg_queue_timeline_text($input['comment_text'] ?? '');
            $pinned = !empty($input['is_pinned']);
            $comment = mg_queue_timeline_comment($pdo, $note, $actorId, $text, $pinned);
            $metadata += ['comment_id' => $comment['id'], 'is_pinned' => $pinned];
            mg_queue_timeline_notice($pdo, $note, $actorId, $pinned ? 'case_comment_pinned' : 'case_comment', $pinned ? 'Pinned case comment added' : 'Case comment added', 'An internal admin comment was added to a queue case.', $metadata);
        } else {
            $commentId = mg_admin_queue_timeline_comment_id($input['comment_id'] ?? null);
            mg_queue_timeline_pin($pdo, $note, $commentId, $action === 'pin_comment');
            $metadata += ['comment_id' => $commentId];
            mg_queue_timeline_notice($pdo, $note, $actorId, 'case_comment_pinned', $action === 'pin_comment' ? 'Case comment pinned' : 'Case comment unpinned', 'An internal case comment pin state was updated.', $metadata);
        }
        mg_audit('admin_queue_timeline_' . $action, 'user', $metadata, $actorId);
        mg_event('admin.queue_timeline.' . $action, $metadata + ['admin_user_id' => $actorId], $actorId);
        mg_security_log('info', 'admin.queue_timeline.updated', 'Admin queue timeline updated.', $metadata, $actorId);
        $pdo->commit();
        $fresh = mg_queue_timeline_note($pdo, $noteId, false);
        $timeline = mg_queue_timeline_build($pdo, $fresh);
        header('Cache-Control: private, no-store, max-age=0');
        header('Vary: Cookie, Authorization');
        mg_ok($timeline, 'Case timeline updated.');
    }

    mg_fail('Method not allowed.', 405);
} catch (MgAdminAccountException $error) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    mg_fail($error->getMessage(), $error->httpStatus());
} catch (Throwable $error) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    mg_security_log('error', 'admin.queue_timeline.failed', 'Admin queue timeline request failed.', ['exception_class' => $error::class], $actorId);
    mg_fail('Unable to process case timeline request.', 500);
}
