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
            throw new MgMcpAutomationGrantException('Your session expired. Refresh the page and try again.', 419, 'MCP_AUTOMATION_CSRF_FAILED');
        }
        $action = strtolower(trim((string)($_POST['action'] ?? '')));
        if ($action === 'create_grant') {
            mg_mcp_automation_create_grant($pdo, $user, $_POST);
            header('Location: /account-agent-automations.php?created=1', true, 303);
            exit;
        }
        if ($action === 'transition_grant') {
            $result = mg_mcp_automation_transition_grant(
                $pdo,
                $user,
                trim((string)($_POST['grant_id'] ?? '')),
                trim((string)($_POST['transition'] ?? '')),
                trim((string)($_POST['reason'] ?? ''))
            );
            header('Location: /account-agent-automations.php?status=' . rawurlencode((string)$result['status']), true, 303);
            exit;
        }
        throw new MgMcpAutomationGrantException('Unknown automation-grant action.');
    } catch (MgMcpAutomationGrantException $error) {
        $errorMessage = $error->getMessage();
    } catch (Throwable $error) {
        mg_security_log('error', 'mcp.automation_grant.owner_action_failed', 'Automation grant owner action failed.', [
            'exception_class' => $error::class,
            'exception_message' => mb_substr($error->getMessage(), 0, 500),
        ], (int)$user['id']);
        $errorMessage = 'The automation grant action could not be completed.';
    }
}

if (isset($_GET['created'])) {
    $notice = 'Draft automation grant created. Review it before activation.';
} elseif (isset($_GET['status'])) {
    $notice = match ((string)$_GET['status']) {
        'active' => 'Automation grant activated. Runtime execution remains disabled in Phase 4A.',
        'paused' => 'Automation grant paused and any future queued work will be cancellation-requested.',
        'revoked' => 'Automation grant permanently revoked.',
        default => 'Automation grant updated.',
    };
}

$schemaReady = false;
$connections = [];
$grants = [];
try {
    $schemaReady = mg_mcp_automation_schema_ready($pdo);
    if ($schemaReady) {
        $connections = mg_mcp_automation_owner_connections($pdo, (int)$user['id']);
        $grants = mg_mcp_automation_owner_grants($pdo, (int)$user['id']);
    } elseif ($errorMessage === '') {
        $errorMessage = 'The MCP automation foundation migration has not been imported.';
    }
} catch (MgMcpAutomationGrantException $error) {
    if ($errorMessage === '') {
        $errorMessage = $error->getMessage();
    }
}

$summary = mg_mcp_automation_grant_summary($grants);
$playbookCatalog = mg_mcp_automation_playbook_catalog();
$connectionById = [];
foreach ($connections as $connection) {
    $connectionById[(string)$connection['id']] = $connection;
}

$formatMoney = static function (?int $cents, ?string $currency): string {
    if ($cents === null) {
        return 'Not set';
    }
    return ($currency ?: 'USD') . ' ' . number_format($cents / 100, 2);
};
$formatFrequency = static function (?int $seconds): string {
    if ($seconds === null) {
        return 'Not set';
    }
    if ($seconds % 86400 === 0) {
        return (string)($seconds / 86400) . ' day(s)';
    }
    return (string)max(1, intdiv($seconds, 3600)) . ' hour(s)';
};

$page_title = 'Agent Automations | Microgifter';
$page_section = 'account';
$header_mode = 'account';
$agent_tab = 'agent-automations';
$can_merchant_nav = true;
$page_body_class = 'mg-agent-automations-page';
$page_styles = [
    '/assets/css/agent-workspace-layout.css',
    '/assets/css/mcp-automation-grants.css?v=20260721-phase4a',
];
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/mcp-automations/account-page-view.php';
require __DIR__ . '/includes/footer.php';
