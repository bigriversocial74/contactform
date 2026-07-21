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
        if (in_array($action, ['emergency_pause_all', 'pause_connection'], true) && (string)($_POST['confirm'] ?? '') !== '1') {
            throw new MgMcpAutomationGrantException('Confirm the emergency control before continuing.', 422, 'MCP_AUTOMATION_CONTROL_CONFIRMATION_REQUIRED');
        }
        if ($action === 'emergency_pause_all') {
            $result = mg_mcp_automation_emergency_pause_all($pdo, $user, (string)($_POST['reason'] ?? ''));
            header('Location: /account-agent-automation-operations.php?paused_all=1&grants=' . (int)$result['grants_paused'] . '&runs=' . (int)$result['runs_cancel_requested'], true, 303);
            exit;
        }
        if ($action === 'pause_connection') {
            $result = mg_mcp_automation_pause_connection($pdo, $user, (string)($_POST['connection_id'] ?? ''), (string)($_POST['reason'] ?? ''));
            header('Location: /account-agent-automation-operations.php?paused_connection=1&grants=' . (int)$result['grants_paused'], true, 303);
            exit;
        }
        if ($action === 'cancel_run') {
            $result = mg_mcp_automation_request_run_cancellation($pdo, $user, (string)($_POST['run_id'] ?? ''), (string)($_POST['reason'] ?? ''));
            header('Location: /account-agent-automation-operations.php?cancel=' . (!empty($result['cancellation_requested']) ? '1' : '0'), true, 303);
            exit;
        }
        throw new MgMcpAutomationGrantException('Unknown MCP operations action.');
    } catch (MgMcpAutomationGrantException $error) {
        $errorMessage = $error->getMessage();
    } catch (Throwable $error) {
        mg_security_log('error', 'mcp.automation_operations.owner_action_failed', 'MCP automation operations action failed.', [
            'exception_class' => $error::class,
            'exception_message' => mb_substr($error->getMessage(), 0, 500),
        ], (int)$user['id']);
        $errorMessage = 'The MCP automation operations action could not be completed.';
    }
}

if (isset($_GET['paused_all'])) {
    $notice = 'Emergency pause applied to ' . (int)($_GET['grants'] ?? 0) . ' active grant(s); cancellation was requested for ' . (int)($_GET['runs'] ?? 0) . ' mutable run(s).';
} elseif (isset($_GET['paused_connection'])) {
    $notice = 'Connection automation pause applied to ' . (int)($_GET['grants'] ?? 0) . ' active grant(s).';
} elseif (isset($_GET['cancel'])) {
    $notice = (string)$_GET['cancel'] === '1'
        ? 'Run cancellation requested. A future worker must honor the cancellation before any execution attempt.'
        : 'The run is already terminal; the cancellation request was recorded as operations evidence.';
}

$schemaReady = false;
$snapshot = [
    'counts' => ['grants' => [], 'definitions' => [], 'triggers' => [], 'runs' => [], 'actions' => [], 'receipts' => 0, 'cancellation_requests' => 0, 'due_schedules' => 0],
    'connections' => [], 'runs' => [], 'events' => [], 'health' => [],
    'execution_enabled' => false, 'scheduler_enabled' => false, 'worker_enabled' => false,
];
try {
    $schemaReady = mg_mcp_automation_schema_ready($pdo);
    if ($schemaReady) {
        $snapshot = mg_mcp_automation_owner_operations_snapshot($pdo, (int)$user['id']);
    } elseif ($errorMessage === '') {
        $errorMessage = 'The MCP automation foundation migration has not been imported.';
    }
} catch (Throwable $error) {
    if ($errorMessage === '') $errorMessage = 'MCP automation operations records are unavailable.';
}

$countTotal = static fn(array $counts): int => array_sum(array_map('intval', $counts));
$grantTotal = $countTotal((array)$snapshot['counts']['grants']);
$activeGrantCount = (int)($snapshot['counts']['grants']['active'] ?? 0);
$definitionTotal = $countTotal((array)$snapshot['counts']['definitions']);
$runTotal = $countTotal((array)$snapshot['counts']['runs']);
$proposedActionCount = (int)($snapshot['counts']['actions']['proposed'] ?? 0);

$page_title = 'Agent Automation Operations | Microgifter';
$page_section = 'account';
$header_mode = 'account';
$agent_tab = 'agent-automations';
$can_merchant_nav = true;
$page_body_class = 'mg-agent-automation-operations-page';
$page_styles = [
    '/assets/css/agent-workspace-layout.css',
    '/assets/css/mcp-automation-grants.css?v=20260721-phase4a',
    '/assets/css/mcp-automation-definitions.css?v=20260721-phase4b',
    '/assets/css/mcp-automation-schedules.css?v=20260721-phase4c',
    '/assets/css/mcp-automation-operations.css?v=20260721-phase4d',
];
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/mcp-automations/operations-page-view.php';
require __DIR__ . '/includes/footer.php';
