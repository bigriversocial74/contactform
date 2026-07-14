<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';

$allowedPersonalViews = ['home','contacts','birthdays','calendar','plans','reminders','group','memory','settings'];
$agent_personal_view = strtolower(trim((string) ($_GET['view'] ?? 'home')));
if (!in_array($agent_personal_view, $allowedPersonalViews, true)) {
    $agent_personal_view = 'home';
}

$page_title = 'Personal Gifting Agent | Microgifter';
$page_section = 'agent';
$header_mode = 'agent';
$agent_tab = 'agent';
$page_body_class = 'mg-personal-gifting-agent-page';
$page_styles = [
    '/assets/css/agent-workspace-layout.css',
    '/assets/css/personal-gifting-agent.css',
];
$page_scripts = [
    '/assets/js/agent-workspace.js',
    '/assets/js/personal-gifting-agent.js',
    '/assets/js/personal-gifting-agent-actions.js',
];

require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/agent-workspace.php';
require __DIR__ . '/includes/footer.php';
