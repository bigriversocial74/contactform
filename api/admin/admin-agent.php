<?php
declare(strict_types=1);

require_once __DIR__ . '/_user_management.php';
require_once dirname(__DIR__, 2) . '/includes/admin-agent-runtime.php';

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$actor = mg_require_api_user();
$actorId = (int)$actor['id'];
$pdo = mg_db();

function mg_admin_agent_api_has(array $actor, string $permission): bool
{
    if (mg_admin_account_actor_has($actor, $permission)) return true;
    $fallbacks = match ($permission) {
        'admin.admin_agent.view' => ['admin.operations_command.view','admin.health.view','admin.audit.view','security.logs.view','admin.users.manage'],
        'admin.admin_agent.chat' => ['admin.operations_command.view','admin.users.manage'],
        'admin.admin_agent.manage' => ['admin.operations_command.manage','admin.settings.manage','admin.users.manage'],
        'admin.admin_agent.actions' => ['admin.operations_command.manage','admin.settings.manage','admin.users.manage'],
        default => [],
    };
    foreach ($fallbacks as $fallback) {
        if (mg_admin_account_actor_has($actor, $fallback)) return true;
    }
    return false;
}

function mg_admin_agent_api_require(array $actor, string $permission): void
{
    if (mg_admin_agent_api_has($actor, $permission)) return;
    mg_audit('permission_denied', 'security', ['permission'=>$permission,'area'=>'main_admin_agent'], (int)$actor['id']);
    mg_security_log('warning', 'admin_agent.permission_denied', 'Main Admin Agent permission denied.', ['permission'=>$permission], (int)$actor['id']);
    mg_fail('Permission denied.', 403);
}

function mg_admin_agent_api_options(array $source): array
{
    return [
        'after'=>max(0, (int)($source['after'] ?? 0)),
        'event_limit'=>max(10, min(200, (int)($source['event_limit'] ?? 100))),
        'domain'=>preg_replace('/[^a-z0-9_]/', '', strtolower((string)($source['domain'] ?? ''))),
        'finding_status'=>(string)($source['finding_status'] ?? 'active'),
        'thread_id'=>trim((string)($source['thread_id'] ?? '')),
    ];
}

function mg_admin_agent_api_permissions(array $actor): array
{
    return [
        'chat'=>mg_admin_agent_api_has($actor, 'admin.admin_agent.chat'),
        'manage'=>mg_admin_agent_api_has($actor, 'admin.admin_agent.manage'),
        'actions'=>mg_admin_agent_api_has($actor, 'admin.admin_agent.actions'),
    ];
}

try {
    if ($method === 'GET') {
        mg_rate_limit('admin.agent.read', 'user:' . $actorId, 240, 60);
        mg_admin_agent_api_require($actor, 'admin.admin_agent.view');
        $schema = mg_admin_agent_schema_state($pdo);
        if ($schema['ready']) {
            $lastScan = mg_admin_agent_last_scan($pdo);
            $completedAt = (string)($lastScan['completed_at'] ?? '');
            $stale = $lastScan === null || $completedAt === '' || strtotime($completedAt . ' UTC') < time() - 300;
            if ($stale && (string)($_GET['skip_scan'] ?? '') !== '1') {
                mg_admin_agent_scan_runtime($pdo, ['trigger_source'=>'workspace_load','initiated_by_user_id'=>$actorId]);
            }
        }
        $payload = mg_admin_agent_state_runtime($pdo, $actorId, mg_admin_agent_api_options($_GET));
        $payload['permissions'] = mg_admin_agent_api_permissions($actor);
        header('Cache-Control: private, no-store, max-age=0');
        header('Vary: Cookie, Authorization');
        mg_ok($payload, 'Main Admin Agent loaded.');
    }

    if ($method === 'POST') {
        mg_rate_limit('admin.agent.write', 'user:' . $actorId, 90, 60);
        $input = mg_input();
        mg_require_csrf_for_write($input);
        mg_admin_agent_api_require($actor, 'admin.admin_agent.view');
        if (!mg_admin_agent_schema_ready($pdo)) {
            mg_fail('Main Admin Agent SQL migration is required.', 409, ['schema'=>mg_admin_agent_schema_state($pdo)]);
        }
        $action = strtolower(trim((string)($input['action'] ?? 'send_message')));
        $result = null;
        if ($action === 'send_message') {
            mg_admin_agent_api_require($actor, 'admin.admin_agent.chat');
            $result = mg_admin_agent_send_runtime($pdo, $actorId, $input);
        } elseif ($action === 'run_scan') {
            mg_admin_agent_api_require($actor, 'admin.admin_agent.manage');
            $result = mg_admin_agent_scan_runtime($pdo, ['trigger_source'=>'manual','initiated_by_user_id'=>$actorId]);
        } elseif ($action === 'new_thread') {
            mg_admin_agent_api_require($actor, 'admin.admin_agent.chat');
            $result = mg_admin_agent_new_thread($pdo, $actorId);
        } elseif ($action === 'finding_action') {
            mg_admin_agent_api_require($actor, 'admin.admin_agent.manage');
            $result = mg_admin_agent_apply_finding_action_runtime($pdo, $actorId, $input);
        } elseif ($action === 'request_action') {
            mg_admin_agent_api_require($actor, 'admin.admin_agent.actions');
            $result = mg_admin_agent_request_action($pdo, $actorId, $input);
        } else {
            throw new InvalidArgumentException('Unknown Main Admin Agent action.');
        }
        $options = mg_admin_agent_api_options($input);
        if (is_array($result['thread'] ?? null) && !empty($result['thread']['id'])) $options['thread_id'] = (string)$result['thread']['id'];
        if ($action === 'new_thread' && !empty($result['id'])) $options['thread_id'] = (string)$result['id'];
        $state = mg_admin_agent_state_runtime($pdo, $actorId, $options);
        $state['permissions'] = mg_admin_agent_api_permissions($actor);
        header('Cache-Control: private, no-store, max-age=0');
        header('Vary: Cookie, Authorization');
        mg_ok(['result'=>$result,'state'=>$state], 'Main Admin Agent action completed.');
    }

    mg_fail('Method not allowed.', 405);
} catch (InvalidArgumentException $error) {
    mg_fail($error->getMessage(), 422);
} catch (Throwable $error) {
    mg_security_log('error', 'admin_agent.request_failed', 'Main Admin Agent request failed.', ['exception_class'=>$error::class], $actorId);
    mg_fail('Unable to process the Main Admin Agent request.', 500);
}
