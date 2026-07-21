<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/mcp-automations.php';

$user = mg_require_auth();
$pdo = mg_db();
$notice = '';
$errorMessage = '';

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
    try {
        if (!mg_verify_csrf(is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : null)) {
            throw new MgMcpAutomationGrantException('Your session expired. Refresh and try again.', 419, 'MCP_AUTOMATION_CSRF_FAILED');
        }
        $action = strtolower(trim((string)($_POST['action'] ?? '')));
        if ($action === 'update_schedule_authority') {
            mg_mcp_automation_update_schedule_authority($pdo, $user, (string)($_POST['grant_id'] ?? ''), $_POST);
            header('Location: /account-agent-automation-schedules.php?authority=1', true, 303);
            exit;
        }
        if ($action === 'configure_schedule') {
            mg_mcp_automation_configure_schedule($pdo, $user, (string)($_POST['automation_id'] ?? ''), $_POST);
            header('Location: /account-agent-automation-schedules.php?configured=1', true, 303);
            exit;
        }
        if ($action === 'remove_schedule') {
            mg_mcp_automation_remove_schedule($pdo, $user, (string)($_POST['automation_id'] ?? ''), (string)($_POST['reason'] ?? ''));
            header('Location: /account-agent-automation-schedules.php?removed=1', true, 303);
            exit;
        }
        if ($action === 'evaluate_due') {
            $result = mg_mcp_automation_evaluate_due_schedules($pdo, $user, 10);
            header('Location: /account-agent-automation-schedules.php?evaluated=' . count($result['completed']) . '&failed=' . count($result['failed']), true, 303);
            exit;
        }
        throw new MgMcpAutomationGrantException('Unknown scheduled-simulation action.');
    } catch (MgMcpAutomationGrantException $error) {
        $errorMessage = $error->getMessage();
    } catch (Throwable $error) {
        mg_security_log('error', 'mcp.automation_schedule.owner_action_failed', 'Scheduled-simulation owner action failed.', [
            'exception_class' => $error::class,
            'exception_message' => mb_substr($error->getMessage(), 0, 500),
        ], (int)$user['id']);
        $errorMessage = 'The scheduled-simulation action could not be completed.';
    }
}

if (isset($_GET['authority'])) {
    $notice = 'Scheduled-simulation authority updated. No scheduler or worker was enabled.';
} elseif (isset($_GET['configured'])) {
    $notice = 'Simulation schedule configured. It will fire only when you manually evaluate due schedules.';
} elseif (isset($_GET['removed'])) {
    $notice = 'Simulation schedule paused and removed from the due list.';
} elseif (isset($_GET['evaluated'])) {
    $notice = (int)$_GET['evaluated'] . ' due simulation(s) completed; ' . (int)($_GET['failed'] ?? 0) . ' failed. No Microgifter action was executed.';
}

$schemaReady = false;
$grants = [];
$definitions = [];
$schedules = [];
$runs = [];
try {
    $schemaReady = mg_mcp_automation_schema_ready($pdo);
    if ($schemaReady) {
        $grants = mg_mcp_automation_owner_grants($pdo, (int)$user['id']);
        $definitions = mg_mcp_automation_owner_definitions($pdo, (int)$user['id']);
        $schedules = mg_mcp_automation_owner_schedules($pdo, (int)$user['id']);
        $runs = mg_mcp_automation_recent_scheduled_simulations($pdo, (int)$user['id']);
    } elseif ($errorMessage === '') {
        $errorMessage = 'The MCP automation foundation migration has not been imported.';
    }
} catch (Throwable $error) {
    if ($errorMessage === '') {
        $errorMessage = 'Scheduled-simulation records are unavailable.';
    }
}

$schedulableDefinitions = array_values(array_filter($definitions, static fn(array $definition): bool =>
    (string)$definition['status'] === 'active' && (string)$definition['grant']['status'] === 'active'
));
$activeScheduleCount = count(array_filter($schedules, static fn(array $schedule): bool => (string)$schedule['status'] === 'active'));
$dueCount = count(array_filter($schedules, static fn(array $schedule): bool =>
    (string)$schedule['status'] === 'active'
    && $schedule['next_due_at'] !== null
    && strtotime((string)$schedule['next_due_at'] . ' UTC') <= time()
));

$page_title = 'Agent Automation Schedules | Microgifter';
$page_section = 'account';
$header_mode = 'account';
$agent_tab = 'agent-automations';
$can_merchant_nav = true;
$page_body_class = 'mg-agent-automation-schedules-page';
$page_styles = [
    '/assets/css/agent-workspace-layout.css',
    '/assets/css/mcp-automation-grants.css?v=20260721-phase4a',
    '/assets/css/mcp-automation-definitions.css?v=20260721-phase4b',
    '/assets/css/mcp-automation-schedules.css?v=20260721-phase4c',
];
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/mcp-automations/schedules-page-view.php';
require __DIR__ . '/includes/footer.php';
