<?php
declare(strict_types=1);

require_once __DIR__ . '/_user_management.php';
require_once __DIR__ . '/_queue_alerts.php';
require_once __DIR__ . '/_ops_incidents.php';
require_once __DIR__ . '/_ops_reviews.php';

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$actor = mg_require_api_user();
$actorId = (int)$actor['id'];
$pdo = mg_db();

function mg_admin_ops_review_has(array $actor, string $permission): bool
{
    return mg_admin_account_actor_has($actor, $permission)
        || mg_admin_account_actor_has($actor, 'admin.operations_reviews.manage')
        || mg_admin_account_actor_has($actor, 'admin.operations_incidents.manage')
        || mg_admin_account_actor_has($actor, 'admin.operations_command.manage')
        || mg_admin_account_actor_has($actor, 'admin.users.manage');
}

function mg_admin_ops_review_require(array $actor, string $permission): void
{
    if (!mg_admin_ops_review_has($actor, $permission)) {
        mg_audit('permission_denied', 'security', ['permission'=>$permission, 'area'=>'admin_ops_reviews'], (int)$actor['id']);
        mg_security_log('warning', 'admin.ops_review.denied', 'Admin incident review permission denied.', ['permission'=>$permission], (int)$actor['id']);
        mg_fail('Permission denied.', 403);
    }
}

try {
    if ($method === 'GET') {
        mg_rate_limit('admin.ops_review.read', 'user:' . $actorId, 180, 60);
        mg_admin_ops_review_require($actor, 'admin.operations_reviews.view');
        $incidentId = trim((string)($_GET['incident_id'] ?? ''));
        $payload = $incidentId !== '' ? mg_ops_review_detail($pdo, mg_ops_review_id($incidentId)) : mg_ops_review_list($pdo);
        mg_audit('admin_ops_reviews_viewed', 'user', ['incident_id'=>$incidentId ?: null], $actorId);
        header('Cache-Control: private, no-store, max-age=0');
        header('Vary: Cookie, Authorization');
        mg_ok($payload, 'Incident reviews loaded.');
    }

    if ($method === 'POST') {
        mg_rate_limit('admin.ops_review.write', 'user:' . $actorId, 60, 60);
        mg_admin_ops_review_require($actor, 'admin.operations_reviews.manage');
        $input = mg_input();
        mg_require_csrf_for_write($input);
        $pdo->beginTransaction();
        $result = mg_ops_review_save($pdo, $actorId, $input);
        mg_audit('admin_ops_review_saved', 'user', $result, $actorId);
        mg_event('admin.ops_review.saved', $result + ['admin_user_id'=>$actorId], $actorId);
        mg_security_log('info', 'admin.ops_review.saved', 'Admin incident review saved.', $result, $actorId);
        $pdo->commit();
        header('Cache-Control: private, no-store, max-age=0');
        header('Vary: Cookie, Authorization');
        mg_ok(['result'=>$result, 'reviews'=>mg_ops_review_list($pdo)], 'Incident review saved.');
    }

    mg_fail('Method not allowed.', 405);
} catch (MgAdminAccountException $error) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    mg_fail($error->getMessage(), $error->httpStatus());
} catch (Throwable $error) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    mg_security_log('error', 'admin.ops_review.failed', 'Admin incident review request failed.', ['exception_class'=>$error::class], $actorId);
    mg_fail('Unable to process incident review request.', 500);
}
