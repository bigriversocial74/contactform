<?php
declare(strict_types=1);

require_once __DIR__ . '/_user_management.php';
require_once dirname(__DIR__, 2) . '/includes/delivery-operations.php';

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$actor = mg_require_api_user();
$actorId = (int)$actor['id'];
$pdo = mg_db();

function mg_delivery_admin_has(array $actor, string $permission): bool
{
    return mg_admin_account_actor_has($actor, $permission)
        || mg_admin_account_actor_has($actor, 'admin.health.view')
        || mg_admin_account_actor_has($actor, 'admin.users.manage');
}

function mg_delivery_admin_require(array $actor, string $permission): void
{
    if (!mg_delivery_admin_has($actor, $permission)) {
        mg_audit('permission_denied', 'security', ['permission'=>$permission,'area'=>'delivery_operations'], (int)$actor['id']);
        mg_fail('Permission denied.', 403);
    }
}

try {
    if ($method === 'GET') {
        mg_rate_limit('admin.delivery_operations.read', 'user:' . $actorId, 180, 60);
        mg_delivery_admin_require($actor, 'delivery.operations.view');
        $summary = mg_delivery_summary($pdo);
        $jobs = !empty($summary['schema_ready']) ? mg_delivery_list_jobs($pdo, $_GET) : [];
        header('Cache-Control: private, no-store, max-age=0');
        header('Vary: Cookie, Authorization');
        mg_ok([
            'summary'=>$summary,
            'jobs'=>$jobs,
            'can_manage'=>mg_delivery_admin_has($actor, 'delivery.operations.manage'),
            'pause_acknowledgement'=>mg_delivery_admin_has($actor, 'delivery.operations.manage') ? (string)mg_delivery_config()['pause_acknowledgement'] : null,
        ], 'Delivery operations loaded.');
    }

    if ($method === 'POST') {
        mg_rate_limit('admin.delivery_operations.write', 'user:' . $actorId, 60, 60);
        mg_delivery_admin_require($actor, 'delivery.operations.manage');
        $input = mg_input();
        mg_require_csrf_for_write($input);
        $action = strtolower(trim((string)($input['action'] ?? '')));
        if ($action === 'clear_pause') {
            $result = mg_delivery_clear_pause($pdo, (string)($input['acknowledgement'] ?? ''), $actorId);
        } elseif (in_array($action, ['retry','cancel','requeue_dead_letter'], true)) {
            $result = mg_delivery_operator_action($pdo, $action, trim((string)($input['job_id'] ?? '')), $actorId);
        } else {
            mg_fail('Unsupported delivery operation.', 422);
        }
        header('Cache-Control: private, no-store, max-age=0');
        mg_ok(['result'=>$result,'summary'=>mg_delivery_summary($pdo)], 'Delivery operation completed.');
    }

    mg_fail('Method not allowed.', 405);
} catch (InvalidArgumentException $error) {
    mg_fail($error->getMessage(), 422);
} catch (RuntimeException $error) {
    mg_fail($error->getMessage(), 409);
} catch (Throwable $error) {
    mg_security_log('error', 'admin.delivery_operations.failed', 'Delivery operations request failed.', ['exception_class'=>$error::class], $actorId);
    mg_fail('Unable to process delivery operations.', 500);
}
