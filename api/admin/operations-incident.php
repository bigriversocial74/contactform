<?php
declare(strict_types=1);

require_once __DIR__ . '/_user_management.php';
require_once __DIR__ . '/_queue_alerts.php';
require_once __DIR__ . '/_ops_incidents.php';

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$actor = mg_require_api_user();
$actorId = (int)$actor['id'];
$pdo = mg_db();

function mg_admin_ops_incident_has(array $actor, string $permission): bool
{
    return mg_admin_account_actor_has($actor, $permission)
        || mg_admin_account_actor_has($actor, 'admin.operations_command.manage')
        || mg_admin_account_actor_has($actor, 'admin.support_queue.manage')
        || mg_admin_account_actor_has($actor, 'admin.users.manage');
}

function mg_admin_ops_incident_require(array $actor, string $permission): void
{
    if (!mg_admin_ops_incident_has($actor, $permission)) {
        mg_audit('permission_denied', 'security', ['permission'=>$permission, 'area'=>'admin_ops_incidents'], (int)$actor['id']);
        mg_security_log('warning', 'admin.ops_incident.denied', 'Admin ops incident permission denied.', ['permission'=>$permission], (int)$actor['id']);
        mg_fail('Permission denied.', 403);
    }
}

try {
    if ($method === 'GET') {
        mg_rate_limit('admin.ops_incident.read', 'user:' . $actorId, 180, 60);
        mg_admin_ops_incident_require($actor, 'admin.operations_incidents.view');
        $payload = mg_ops_incident_payload($pdo);
        mg_audit('admin_ops_incidents_viewed', 'user', ['active_total'=>$payload['summary']['active_total']], $actorId);
        header('Cache-Control: private, no-store, max-age=0');
        header('Vary: Cookie, Authorization');
        mg_ok($payload, 'Operations incidents loaded.');
    }

    if ($method === 'POST') {
        mg_rate_limit('admin.ops_incident.write', 'user:' . $actorId, 60, 60);
        mg_admin_ops_incident_require($actor, 'admin.operations_incidents.manage');
        $input = mg_input();
        mg_require_csrf_for_write($input);
        $action = strtolower(trim((string)($input['action'] ?? 'declare')));
        $pdo->beginTransaction();
        if ($action === 'declare') {
            $result = mg_ops_incident_declare($pdo, $actorId, $input);
        } else {
            $result = mg_ops_incident_apply($pdo, $actorId, $input);
        }
        mg_audit('admin_ops_incident_' . $action, 'user', $result, $actorId);
        mg_event('admin.ops_incident.' . $action, $result + ['admin_user_id'=>$actorId], $actorId);
        mg_security_log('info', 'admin.ops_incident.updated', 'Admin ops incident action completed.', $result, $actorId);
        $pdo->commit();
        header('Cache-Control: private, no-store, max-age=0');
        header('Vary: Cookie, Authorization');
        mg_ok(['result'=>$result, 'incidents'=>mg_ops_incident_payload($pdo)], 'Operations incident updated.');
    }

    mg_fail('Method not allowed.', 405);
} catch (MgAdminAccountException $error) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    mg_fail($error->getMessage(), $error->httpStatus());
} catch (Throwable $error) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    mg_security_log('error', 'admin.ops_incident.failed', 'Admin ops incident request failed.', ['exception_class'=>$error::class], $actorId);
    mg_fail('Unable to process operations incident request.', 500);
}
