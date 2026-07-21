<?php
declare(strict_types=1);

$user = mg_require_auth();
$pdo = mg_db();
$notice = '';
$errorMessage = '';

require __DIR__ . '/native-status/account-actions.php';

try {
    $handoffs = mg_mcp_native_status_list_for_owner($pdo, (int)$user['id']);
} catch (Throwable $error) {
    $handoffs = [];
    $errorMessage = $error instanceof MgMcpDraftException ? $error->getMessage() : 'Handoff status is temporarily unavailable.';
}

$summary = array_fill_keys(MG_MCP_NATIVE_STATE_CLASSES, 0);
foreach ($handoffs as $handoff) {
    $key = (string)($handoff['native']['state_class'] ?? 'unknown');
    if (isset($summary[$key])) $summary[$key]++;
}

$page_title = 'Agent Handoffs | Microgifter';
$page_section = 'account';
$header_mode = 'account';
$agent_tab = 'agent-drafts';
$can_merchant_nav = true;
$page_body_class = 'mg-agent-handoffs-page';
$page_styles = [
    '/assets/css/agent-workspace-layout.css',
    '/assets/css/mcp-drafts.css?v=20260720-phase3b',
    '/assets/css/mcp-native-status.css?v=20260721-phase3c',
];
require dirname(__DIR__) . '/header.php';
require __DIR__ . '/native-status-view.php';
require dirname(__DIR__) . '/footer.php';
