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
        if ($action === 'create_definition') {
            mg_mcp_automation_create_definition($pdo, $user, $_POST);
            header('Location: /account-agent-automation-definitions.php?created=1', true, 303); exit;
        }
        if ($action === 'transition_definition') {
            $result = mg_mcp_automation_transition_definition($pdo, $user, (string)($_POST['automation_id'] ?? ''), (string)($_POST['transition'] ?? ''), (string)($_POST['reason'] ?? ''));
            header('Location: /account-agent-automation-definitions.php?status=' . rawurlencode((string)$result['status']), true, 303); exit;
        }
        if ($action === 'simulate_definition') {
            $result = mg_mcp_automation_run_simulation($pdo, $user, (string)($_POST['automation_id'] ?? ''));
            header('Location: /account-agent-automation-definitions.php?simulated=' . rawurlencode((string)$result['id']), true, 303); exit;
        }
        throw new MgMcpAutomationGrantException('Unknown automation-definition action.');
    } catch (MgMcpAutomationGrantException $error) {
        $errorMessage = $error->getMessage();
    } catch (Throwable $error) {
        mg_security_log('error', 'mcp.automation_definition.owner_action_failed', 'Automation definition owner action failed.', ['exception_class'=>$error::class,'exception_message'=>mb_substr($error->getMessage(),0,500)], (int)$user['id']);
        $errorMessage = 'The automation-definition action could not be completed.';
    }
}
if (isset($_GET['created'])) $notice = 'Draft automation definition created. Activate it before running a simulation.';
elseif (isset($_GET['simulated'])) $notice = 'Policy simulation completed. No Microgifter action was executed.';
elseif (isset($_GET['status'])) $notice = 'Automation definition updated to ' . preg_replace('/[^a-z_]/', '', (string)$_GET['status']) . '.';
$schemaReady = mg_mcp_automation_schema_ready($pdo);
$grants = $schemaReady ? mg_mcp_automation_owner_grants($pdo, (int)$user['id']) : [];
$definitions = $schemaReady ? mg_mcp_automation_owner_definitions($pdo, (int)$user['id']) : [];
$runs = $schemaReady ? mg_mcp_automation_recent_simulations($pdo, (int)$user['id']) : [];
$summary = mg_mcp_automation_definition_summary($definitions, $runs);
$playbookCatalog = mg_mcp_automation_playbook_catalog();
$activeGrants = array_values(array_filter($grants, static fn(array $grant): bool => (string)$grant['status'] === 'active'));
$page_title = 'Agent Automation Definitions | Microgifter';
$page_section = 'account'; $header_mode = 'account'; $agent_tab = 'agent-automations'; $can_merchant_nav = true;
$page_body_class = 'mg-agent-automation-definitions-page';
$page_styles = ['/assets/css/agent-workspace-layout.css','/assets/css/mcp-automation-grants.css?v=20260721-phase4a','/assets/css/mcp-automation-definitions.css?v=20260721-phase4b'];
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/mcp-automations/definitions-page-view.php';
require __DIR__ . '/includes/footer.php';
