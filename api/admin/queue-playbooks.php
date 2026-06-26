<?php
declare(strict_types=1);

require_once __DIR__ . '/_user_management.php';
require_once __DIR__ . '/_queue_alerts.php';
require_once __DIR__ . '/_queue_playbooks.php';

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$actor = mg_require_api_user();
$actorId = (int)$actor['id'];
$pdo = mg_db();

function mg_admin_queue_playbooks_has(array $actor, string $permission): bool
{
    return mg_admin_account_actor_has($actor, $permission)
        || mg_admin_account_actor_has($actor, 'admin.support_queue.manage')
        || mg_admin_account_actor_has($actor, 'admin.user_notes.manage')
        || mg_admin_account_actor_has($actor, 'admin.users.manage');
}

function mg_admin_queue_playbooks_require(array $actor, string $permission): void
{
    if (!mg_admin_queue_playbooks_has($actor, $permission)) {
        mg_audit('permission_denied', 'security', ['permission' => $permission, 'area' => 'admin_queue_playbooks'], (int)$actor['id']);
        mg_security_log('warning', 'admin.queue_playbooks.denied', 'Admin queue playbook permission denied.', ['permission' => $permission], (int)$actor['id']);
        mg_fail('Permission denied.', 403);
    }
}

function mg_admin_queue_playbook_note_id(mixed $value): string
{
    $id = trim((string)$value);
    if (preg_match('/^[a-f0-9-]{20,60}$/i', $id) !== 1) {
        throw new MgAdminAccountException('Invalid queue note identifier.', 422);
    }
    return $id;
}

function mg_admin_queue_playbook_note_payload(array $note): array
{
    return [
        'id' => (string)$note['public_id'],
        'playbook_slug' => $note['playbook_slug'] ?? null,
        'resolution_template_slug' => $note['resolution_template_slug'] ?? null,
        'playbook_checklist' => !empty($note['playbook_checklist_json']) ? json_decode((string)$note['playbook_checklist_json'], true) : null,
        'playbook_applied_at' => $note['playbook_applied_at'] ?? null,
        'status' => (string)$note['status'],
        'flag_state' => (string)$note['flag_state'],
        'category' => (string)$note['category'],
        'priority' => (string)$note['priority'],
        'sla_status' => $note['sla_status'] ?? null,
    ];
}

try {
    if ($method === 'GET') {
        mg_rate_limit('admin.queue_playbooks.read', 'user:' . $actorId, 180, 60);
        mg_admin_queue_playbooks_require($actor, 'admin.queue_playbooks.view');
        $note = null;
        if (isset($_GET['note_id']) && trim((string)$_GET['note_id']) !== '') {
            $note = mg_queue_note_by_public_id($pdo, mg_admin_queue_playbook_note_id($_GET['note_id']), false);
        }
        $payload = mg_queue_playbook_payload($note);
        if ($note) {
            $payload['note'] = mg_admin_queue_playbook_note_payload($note);
        }
        header('Cache-Control: private, no-store, max-age=0');
        header('Vary: Cookie, Authorization');
        mg_ok($payload, 'Queue playbooks loaded.');
    }

    if ($method === 'POST') {
        mg_rate_limit('admin.queue_playbooks.write', 'user:' . $actorId, 90, 60);
        mg_admin_queue_playbooks_require($actor, 'admin.queue_playbooks.manage');
        $input = mg_input();
        mg_require_csrf_for_write($input);
        $action = strtolower(trim((string)($input['action'] ?? 'apply_playbook')));
        if (!in_array($action, ['apply_playbook','apply_template','update_checklist'], true)) {
            throw new MgAdminAccountException('Invalid playbook action.', 422);
        }
        $reason = mg_admin_account_reason($input['reason'] ?? 'Admin queue playbook action.');
        $noteId = mg_admin_queue_playbook_note_id($input['note_id'] ?? null);
        $pdo->beginTransaction();
        $note = mg_queue_note_by_public_id($pdo, $noteId, true);
        $result = null;
        if ($action === 'apply_playbook') {
            $slug = mg_queue_playbook_slug($input['playbook_slug'] ?? null, mg_queue_playbook_library());
            $result = mg_queue_apply_playbook($pdo, $note, $actorId, $slug, $reason);
        }
        if ($action === 'apply_template') {
            $slug = mg_queue_template_slug($input['template_slug'] ?? null, mg_queue_resolution_templates());
            $result = mg_queue_apply_template($pdo, $note, $actorId, $slug, $reason);
        }
        if ($action === 'update_checklist') {
            $checklist = is_array($input['checklist'] ?? null) ? $input['checklist'] : [];
            $result = mg_queue_update_checklist($pdo, $note, $actorId, $checklist, $reason);
        }
        $metadata = ['note_id' => $noteId, 'target_user_id' => (int)$note['target_user_id'], 'action' => $action, 'reason' => $reason];
        mg_audit('admin_queue_playbook_' . $action, 'user', $metadata, $actorId);
        mg_event('admin.queue_playbook.' . $action, $metadata + ['admin_user_id' => $actorId], $actorId);
        mg_security_log('info', 'admin.queue_playbook.updated', 'Admin queue playbook action completed.', $metadata, $actorId);
        $pdo->commit();
        $freshNote = mg_queue_note_by_public_id($pdo, $noteId, false);
        header('Cache-Control: private, no-store, max-age=0');
        header('Vary: Cookie, Authorization');
        mg_ok(['result' => $result, 'note' => mg_admin_queue_playbook_note_payload($freshNote), 'playbooks' => mg_queue_playbook_payload($freshNote)], 'Queue playbook action completed.');
    }

    mg_fail('Method not allowed.', 405);
} catch (MgAdminAccountException $error) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    mg_fail($error->getMessage(), $error->httpStatus());
} catch (Throwable $error) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    mg_security_log('error', 'admin.queue_playbook.failed', 'Admin queue playbook request failed.', ['exception_class' => $error::class], $actorId);
    mg_fail('Unable to process queue playbook request.', 500);
}
