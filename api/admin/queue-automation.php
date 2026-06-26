<?php
declare(strict_types=1);

require_once __DIR__ . '/_user_management.php';
require_once __DIR__ . '/_queue_alerts.php';
require_once __DIR__ . '/_queue_sla.php';
require_once __DIR__ . '/_queue_reporting.php';
require_once __DIR__ . '/_queue_automation.php';

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$actor = mg_require_api_user();
$actorId = (int)$actor['id'];
$pdo = mg_db();

function mg_admin_queue_automation_has(array $actor, string $permission): bool
{
    return mg_admin_account_actor_has($actor, $permission)
        || mg_admin_account_actor_has($actor, 'admin.queue_automation.run')
        || mg_admin_account_actor_has($actor, 'admin.support_queue.manage')
        || mg_admin_account_actor_has($actor, 'admin.user_notes.manage')
        || mg_admin_account_actor_has($actor, 'admin.users.manage');
}

function mg_admin_queue_automation_require(array $actor, string $permission): void
{
    if (!mg_admin_queue_automation_has($actor, $permission)) {
        mg_audit('permission_denied', 'security', ['permission' => $permission, 'area' => 'admin_queue_automation'], (int)$actor['id']);
        mg_security_log('warning', 'admin.queue_automation.denied', 'Admin queue automation permission denied.', ['permission' => $permission], (int)$actor['id']);
        mg_fail('Permission denied.', 403);
    }
}

try {
    if ($method === 'GET') {
        mg_rate_limit('admin.queue_automation.read', 'user:' . $actorId, 180, 60);
        mg_admin_queue_automation_require($actor, 'admin.queue_automation.view');
        header('Cache-Control: private, no-store, max-age=0');
        header('Vary: Cookie, Authorization');
        mg_ok(mg_queue_automation_summary($pdo), 'Queue automation summary loaded.');
    }

    if ($method === 'POST') {
        mg_rate_limit('admin.queue_automation.run', 'user:' . $actorId, 12, 60);
        mg_admin_queue_automation_require($actor, 'admin.queue_automation.run');
        $input = mg_input();
        mg_require_csrf_for_write($input);
        $mode = mg_queue_automation_mode($input['run_mode'] ?? 'manual');
        $result = mg_queue_automation_run($pdo, $actorId, $mode);
        mg_audit('admin_queue_automation_run', 'user', $result + ['run_mode' => $mode], $actorId);
        mg_event('admin.queue_automation.run', $result + ['run_mode' => $mode, 'admin_user_id' => $actorId], $actorId);
        mg_security_log('info', 'admin.queue_automation.completed', 'Admin queue automation completed.', $result + ['run_mode' => $mode], $actorId);
        header('Cache-Control: private, no-store, max-age=0');
        header('Vary: Cookie, Authorization');
        mg_ok(['result' => $result, 'automation' => mg_queue_automation_summary($pdo)], 'Queue automation completed.');
    }

    mg_fail('Method not allowed.', 405);
} catch (Throwable $error) {
    mg_security_log('error', 'admin.queue_automation.failed', 'Admin queue automation failed.', ['exception_class' => $error::class], $actorId);
    mg_fail('Unable to process queue automation.', 500);
}
