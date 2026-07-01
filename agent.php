<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
$page_title = 'Microgifter Agent Workspace';
$page_section = 'agent';
$header_mode = 'agent';
$agent_tab = 'agent';
$page_styles = ['/assets/css/agent-workspace-layout.css','/assets/css/agent-desktop-layout.css','/assets/css/agent-strategies.css','/assets/css/agent-approvals.css'];
$page_scripts = ['/assets/js/agent-workspace.js','/assets/js/agent-sidebar.js','/assets/js/agent-tabs.js','/assets/js/agent-categories.js','/assets/js/agent-controls.js','/assets/js/agent-ai-settings.js','/assets/js/agent-strategies.js','/assets/js/agent-approvals.js'];
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/agent-workspace.php';
require __DIR__ . '/includes/footer.php';