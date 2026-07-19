<?php
declare(strict_types=1);

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'GET') {
    require __DIR__ . '/admin-agent-phase6.php';
    return;
}

require_once __DIR__ . '/_user_management.php';
require_once dirname(__DIR__, 2) . '/includes/admin-agent-phase6-readonly.php';

$actor = mg_require_api_user();
$actorId = (int) $actor['id'];
$pdo = mg_db();
$allowed = mg_admin_account_actor_has($actor, 'admin.admin_agent.view')
    || mg_admin_account_actor_has($actor, 'admin.operations_command.view')
    || mg_admin_account_actor_has($actor, 'admin.health.view')
    || mg_admin_account_actor_has($actor, 'admin.audit.view')
    || mg_admin_account_actor_has($actor, 'security.logs.view')
    || mg_admin_account_actor_has($actor, 'admin.users.manage');
if (!$allowed) {
    mg_audit('permission_denied', 'security', ['permission' => 'admin.admin_agent.view', 'area' => 'main_admin_agent_phase6_router'], $actorId);
    mg_fail('Permission denied.', 403);
}

try {
    mg_rate_limit('admin.agent.phase6.readonly', 'user:' . $actorId, 300, 60);
    $options = [
        'after' => max(0, (int) ($_GET['after'] ?? 0)),
        'event_limit' => max(10, min(200, (int) ($_GET['event_limit'] ?? 100))),
        'domain' => preg_replace('/[^a-z0-9_]/', '', strtolower((string) ($_GET['domain'] ?? ''))),
        'finding_status' => (string) ($_GET['finding_status'] ?? 'active'),
        'thread_id' => trim((string) ($_GET['thread_id'] ?? '')),
        'environment_key' => mg_admin_agent_phase6_environment((string) ($_GET['environment_key'] ?? 'production')),
    ];
    $payload = mg_admin_agent_phase6_ready($pdo)
        ? mg_admin_agent_phase6_state_readonly($pdo, $actorId, $options)
        : (mg_admin_agent_phase5_ready($pdo)
            ? mg_admin_agent_phase5_state($pdo, $actorId, $options)
            : (mg_admin_agent_phase4_ready($pdo)
                ? mg_admin_agent_phase4_state($pdo, $actorId, $options)
                : (mg_admin_agent_phase3_ready($pdo)
                    ? mg_admin_agent_phase3_state($pdo, $actorId, $options)
                    : (mg_admin_agent_phase2_ready($pdo)
                        ? mg_admin_agent_phase2_state($pdo, $actorId, $options)
                        : mg_admin_agent_state_runtime($pdo, $actorId, $options)))));
    $payload['phase6_schema'] = mg_admin_agent_phase6_schema_state($pdo);
    $payload['phase6_ready'] = mg_admin_agent_phase6_ready($pdo);
    $payload['permissions'] = [
        'chat' => mg_admin_account_actor_has($actor, 'admin.admin_agent.chat') || mg_admin_account_actor_has($actor, 'admin.operations_command.view') || mg_admin_account_actor_has($actor, 'admin.users.manage'),
        'manage' => mg_admin_account_actor_has($actor, 'admin.admin_agent.manage') || mg_admin_account_actor_has($actor, 'admin.operations_command.manage') || mg_admin_account_actor_has($actor, 'admin.settings.manage') || mg_admin_account_actor_has($actor, 'admin.users.manage'),
        'actions' => mg_admin_account_actor_has($actor, 'admin.admin_agent.actions') || mg_admin_account_actor_has($actor, 'admin.operations_command.manage') || mg_admin_account_actor_has($actor, 'admin.settings.manage') || mg_admin_account_actor_has($actor, 'admin.users.manage'),
        'briefs' => mg_admin_account_actor_has($actor, 'admin.admin_agent.briefs') || mg_admin_account_actor_has($actor, 'admin.notifications.manage') || mg_admin_account_actor_has($actor, 'admin.settings.manage') || mg_admin_account_actor_has($actor, 'admin.users.manage'),
        'recovery' => mg_admin_account_actor_has($actor, 'admin.admin_agent.recovery') || mg_admin_account_actor_has($actor, 'admin.operations_command.manage') || mg_admin_account_actor_has($actor, 'admin.settings.manage') || mg_admin_account_actor_has($actor, 'admin.users.manage'),
        'evidence' => mg_admin_account_actor_has($actor, 'admin.admin_agent.evidence') || mg_admin_account_actor_has($actor, 'admin.operations_command.manage') || mg_admin_account_actor_has($actor, 'admin.settings.manage') || mg_admin_account_actor_has($actor, 'admin.users.manage'),
        'readiness' => true,
        'setup' => mg_admin_account_actor_has($actor, 'admin.admin_agent.setup') || mg_admin_account_actor_has($actor, 'admin.operations_command.manage') || mg_admin_account_actor_has($actor, 'admin.settings.manage') || mg_admin_account_actor_has($actor, 'admin.users.manage'),
        'export' => mg_admin_account_actor_has($actor, 'admin.admin_agent.export') || mg_admin_account_actor_has($actor, 'admin.audit.view') || mg_admin_account_actor_has($actor, 'admin.settings.manage') || mg_admin_account_actor_has($actor, 'admin.users.manage'),
        'execute' => mg_admin_account_actor_has($actor, 'admin.admin_agent.execute'),
    ];
    header('Cache-Control: private, no-store, max-age=0');
    header('Vary: Cookie, Authorization');
    mg_ok($payload, 'Main Admin Agent Phase 6 loaded.');
} catch (Throwable $error) {
    mg_security_log('error', 'admin_agent.phase6_readonly_failed', 'Main Admin Agent Phase 6 read-only request failed.', ['exception_class' => $error::class], $actorId);
    mg_fail('Unable to load the Main Admin Agent Phase 6 workspace.', 500);
}
