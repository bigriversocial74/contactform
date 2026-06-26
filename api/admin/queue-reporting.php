<?php
declare(strict_types=1);

require_once __DIR__ . '/_user_management.php';
require_once __DIR__ . '/_queue_reporting.php';

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$actor = mg_require_api_user();
$actorId = (int)$actor['id'];
$pdo = mg_db();

function mg_admin_queue_reporting_has(array $actor, string $permission): bool
{
    return mg_admin_account_actor_has($actor, $permission)
        || mg_admin_account_actor_has($actor, 'admin.support_queue.manage')
        || mg_admin_account_actor_has($actor, 'admin.user_notes.manage')
        || mg_admin_account_actor_has($actor, 'admin.users.manage');
}

function mg_admin_queue_reporting_require(array $actor, string $permission): void
{
    if (!mg_admin_queue_reporting_has($actor, $permission)) {
        mg_audit('permission_denied', 'security', ['permission' => $permission, 'area' => 'admin_queue_reporting'], (int)$actor['id']);
        mg_security_log('warning', 'admin.queue_reporting.denied', 'Admin queue reporting permission denied.', ['permission' => $permission], (int)$actor['id']);
        mg_fail('Permission denied.', 403);
    }
}

try {
    if ($method === 'GET') {
        mg_rate_limit('admin.queue_reporting.read', 'user:' . $actorId, 180, 60);
        mg_admin_queue_reporting_require($actor, 'admin.queue_reporting.view');
        $days = (int)($_GET['window_days'] ?? 30);
        $payload = mg_queue_reporting_read($pdo, $days);
        mg_audit('admin_queue_reporting_viewed', 'user', ['window_days' => $payload['filters']['window_days']], $actorId);
        mg_event('admin.queue_reporting.viewed', ['window_days' => $payload['filters']['window_days'], 'admin_user_id' => $actorId], $actorId);
        header('Cache-Control: private, no-store, max-age=0');
        header('Vary: Cookie, Authorization');
        mg_ok($payload, 'Queue reporting loaded.');
    }

    if ($method === 'POST') {
        mg_rate_limit('admin.queue_reporting.write', 'user:' . $actorId, 90, 60);
        mg_admin_queue_reporting_require($actor, 'admin.queue_reporting.manage');
        $input = mg_input();
        mg_require_csrf_for_write($input);
        $noteId = mg_queue_reporting_note_id($input['note_id'] ?? null);
        $pdo->beginTransaction();
        $result = mg_queue_reporting_update($pdo, $noteId, $input);
        $metadata = ['note_id' => $noteId] + $result;
        mg_audit('admin_queue_reporting_updated', 'user', $metadata, $actorId);
        mg_event('admin.queue_reporting.updated', $metadata + ['admin_user_id' => $actorId], $actorId);
        mg_security_log('info', 'admin.queue_reporting.updated', 'Queue resolution reporting fields updated.', $metadata, $actorId);
        $pdo->commit();
        header('Cache-Control: private, no-store, max-age=0');
        header('Vary: Cookie, Authorization');
        mg_ok(['result' => $result, 'report' => mg_queue_reporting_read($pdo, (int)($input['window_days'] ?? 30))], 'Queue reporting updated.');
    }

    mg_fail('Method not allowed.', 405);
} catch (MgAdminAccountException $error) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    mg_fail($error->getMessage(), $error->httpStatus());
} catch (Throwable $error) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    mg_security_log('error', 'admin.queue_reporting.failed', 'Admin queue reporting request failed.', ['exception_class' => $error::class], $actorId);
    mg_fail('Unable to process queue reporting request.', 500);
}
