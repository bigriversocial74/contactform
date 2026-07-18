<?php
declare(strict_types=1);

require_once __DIR__ . '/_user_management.php';
require_once dirname(__DIR__, 2) . '/includes/ai/ai-credit-reconciliation.php';

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$actor = mg_require_api_user();
$actorId = (int)$actor['id'];
$pdo = mg_db();

function mg_admin_ai_credit_incident_has(array $actor, string $permission): bool
{
    return mg_admin_account_actor_has($actor, $permission)
        || mg_admin_account_actor_has($actor, 'admin.operations_command.manage')
        || mg_admin_account_actor_has($actor, 'admin.settings.manage')
        || mg_admin_account_actor_has($actor, 'admin.users.manage');
}

function mg_admin_ai_credit_incident_require(array $actor, string $permission): void
{
    if (mg_admin_ai_credit_incident_has($actor, $permission)) return;
    mg_audit('permission_denied', 'security', ['permission'=>$permission,'area'=>'ai_credit_incidents'], (int)$actor['id']);
    mg_security_log('warning', 'admin.ai_credit_incident.denied', 'AI credit incident permission denied.', ['permission'=>$permission], (int)$actor['id']);
    mg_fail('Permission denied.', 403);
}

try {
    if ($method === 'GET') {
        mg_rate_limit('admin.ai_credit_incidents.read', 'user:' . $actorId, 180, 60);
        mg_admin_ai_credit_incident_require($actor, 'admin.ai_credit_incidents.view');
        $payload = mg_ai_reconciliation_queue($pdo, [
            'status'=>$_GET['status'] ?? 'active',
            'incident_type'=>$_GET['incident_type'] ?? '',
            'limit'=>$_GET['limit'] ?? 100,
        ]);
        $payload['permissions'] = [
            'manage'=>mg_admin_ai_credit_incident_has($actor, 'admin.ai_credit_incidents.manage'),
            'retry'=>mg_admin_ai_credit_incident_has($actor, 'admin.ai_credit_incidents.retry'),
        ];
        header('Cache-Control: private, no-store, max-age=0');
        header('Vary: Cookie, Authorization');
        mg_ok($payload, 'AI credit accounting incidents loaded.');
    }

    if ($method === 'POST') {
        mg_rate_limit('admin.ai_credit_incidents.write', 'user:' . $actorId, 60, 60);
        $input = mg_input();
        mg_require_csrf_for_write($input);
        $action = strtolower(trim((string)($input['action'] ?? 'under_review')));
        if ($action === 'retry_debit') {
            mg_admin_ai_credit_incident_require($actor, 'admin.ai_credit_incidents.retry');
            $result = mg_ai_reconciliation_retry_debit($pdo, $actorId, (string)($input['incident_id'] ?? ''));
        } elseif ($action === 'run_reconciliation') {
            mg_admin_ai_credit_incident_require($actor, 'admin.ai_credit_incidents.manage');
            $result = mg_ai_reconciliation_run($pdo, [
                'trigger_source'=>'admin',
                'initiated_by_user_id'=>$actorId,
                'provider_key'=>$input['provider_key'] ?? 'anthropic',
                'days'=>$input['days'] ?? 30,
                'user_id'=>isset($input['user_id']) && (int)$input['user_id'] > 0 ? (int)$input['user_id'] : null,
            ]);
            mg_audit('admin_ai_credit_reconciliation_run', 'user', $result, $actorId);
        } else {
            mg_admin_ai_credit_incident_require($actor, 'admin.ai_credit_incidents.manage');
            $result = mg_ai_reconciliation_apply_action($pdo, $actorId, $input);
        }
        $payload = mg_ai_reconciliation_queue($pdo, [
            'status'=>$input['status_filter'] ?? 'active',
            'incident_type'=>$input['incident_type_filter'] ?? '',
            'limit'=>100,
        ]);
        $payload['permissions'] = [
            'manage'=>mg_admin_ai_credit_incident_has($actor, 'admin.ai_credit_incidents.manage'),
            'retry'=>mg_admin_ai_credit_incident_has($actor, 'admin.ai_credit_incidents.retry'),
        ];
        header('Cache-Control: private, no-store, max-age=0');
        header('Vary: Cookie, Authorization');
        mg_ok(['result'=>$result,'queue'=>$payload], 'AI credit accounting incident updated.');
    }

    mg_fail('Method not allowed.', 405);
} catch (InvalidArgumentException $error) {
    mg_fail($error->getMessage(), 422);
} catch (Throwable $error) {
    mg_security_log('error', 'admin.ai_credit_incident.failed', 'AI credit incident request failed.', ['exception_class'=>$error::class], $actorId);
    mg_fail('Unable to process the AI credit accounting request.', 500);
}
