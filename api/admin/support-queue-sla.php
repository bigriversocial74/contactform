<?php
declare(strict_types=1);

require_once __DIR__ . '/_user_management.php';
require_once __DIR__ . '/_queue_alerts.php';
require_once __DIR__ . '/_queue_sla.php';

$actor = mg_require_api_user();
$actorId = (int)$actor['id'];
$pdo = mg_db();
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

function mg_admin_queue_sla_has(array $actor, string $permission): bool
{
    return mg_admin_account_actor_has($actor, $permission)
        || mg_admin_account_actor_has($actor, 'admin.support_queue.manage')
        || mg_admin_account_actor_has($actor, 'admin.user_notes.manage')
        || mg_admin_account_actor_has($actor, 'admin.users.manage');
}

function mg_admin_queue_sla_require(array $actor, string $permission): void
{
    if (!mg_admin_queue_sla_has($actor, $permission)) {
        mg_audit('permission_denied', 'security', ['permission' => $permission, 'area' => 'admin_queue_sla'], (int)$actor['id']);
        mg_security_log('warning', 'admin.queue_sla.denied', 'Admin queue SLA permission denied.', ['permission' => $permission], (int)$actor['id']);
        mg_fail('Permission denied.', 403);
    }
}

try {
    if ($method === 'GET') {
        mg_rate_limit('admin.queue_sla.read', 'user:' . $actorId, 180, 60);
        mg_admin_queue_sla_require($actor, 'admin.queue_sla.view');
        header('Cache-Control: private, no-store, max-age=0');
        header('Vary: Cookie, Authorization');
        mg_ok(mg_queue_sla_health($pdo), 'Queue SLA health loaded.');
    }

    if ($method === 'POST') {
        mg_rate_limit('admin.queue_sla.write', 'user:' . $actorId, 45, 60);
        mg_admin_queue_sla_require($actor, 'admin.queue_sla.manage');
        $input = mg_input();
        mg_require_csrf_for_write($input);
        $action = strtolower(trim((string)($input['action'] ?? 'apply_rules')));
        if (!in_array($action, ['apply_rules','recalculate','auto_escalate'], true)) {
            mg_fail('Invalid SLA action.', 422);
        }
        $pdo->beginTransaction();
        $result = mg_queue_sla_recalculate($pdo, $actorId, 250);
        $health = mg_queue_sla_health($pdo);
        $metadata = ['action' => $action] + $result + ['health' => $health['summary']];
        mg_audit('admin_queue_sla_' . $action, 'user', $metadata, $actorId);
        mg_event('admin.queue_sla.' . $action, $metadata + ['admin_user_id' => $actorId], $actorId);
        mg_security_log('info', 'admin.queue_sla.applied', 'Admin queue SLA routing rules applied.', $metadata, $actorId);
        $pdo->commit();
        header('Cache-Control: private, no-store, max-age=0');
        header('Vary: Cookie, Authorization');
        mg_ok(['result' => $result, 'health' => $health], 'Queue SLA routing applied.');
    }

    mg_fail('Method not allowed.', 405);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    mg_security_log('error', 'admin.queue_sla.failed', 'Admin queue SLA request failed.', ['exception_class' => $error::class], $actorId);
    mg_fail('Unable to process queue SLA request.', 500);
}
