<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';

$allowedPersonalViews = ['home','design','contacts','birthdays','calendar','plans','scheduled','recurring','reminders','group','requests','bundles','claims','memory','settings'];
$agent_personal_view = strtolower(trim((string) ($_GET['view'] ?? 'home')));
if (!in_array($agent_personal_view, $allowedPersonalViews, true)) {
    $agent_personal_view = 'home';
}
$selected_agent_instance_id = strtolower(trim((string) ($_GET['agent_id'] ?? '')));

$page_title = 'Personal Gifting Agent | Microgifter';
$page_section = 'agent';
$header_mode = 'agent';
$agent_tab = 'agent';
$page_body_class = 'mg-personal-gifting-agent-page' . ($selected_agent_instance_id !== '' ? ' mg-specialized-agent-selected' : '');
$page_styles = [
    '/assets/css/agent-workspace-layout.css',
    '/assets/css/personal-gifting-agent.css',
    '/assets/css/personal-gifting-workflows.css',
    '/assets/css/personal-agent-chat-canvas.css?v=1.0.0',
    '/assets/css/personal-agent-full-canvas.css?v=1.0.0',
    '/assets/css/personal-agent-inline-intro.css?v=1.0.0',
    '/assets/css/personal-agent-chat-history.css?v=1.4.0',
    '/assets/css/unified-agent-sidebar-v2.css?v=1.0.0',
    '/assets/css/agent-mode-switch.css?v=1.0.0',
    '/assets/css/personal-agent-marketplace-cards.css?v=1.1.0',
    '/assets/css/personal-agent-opportunity-actions.css?v=1.0.0',
    '/assets/css/personal-agent-recovery.css?v=1.0.0',
    '/assets/css/gift-action-center-modals.css?v=1.0.0',
    '/assets/css/personal-agent-gift-results.css?v=1.0.0',
    '/assets/css/personal-agent-design-studio.css?v=1.2.0',
    '/assets/css/personal-agent-design-studio-social.css?v=1.0.0',
    '/assets/css/personal-agent-design-studio-calendar.css?v=1.0.0',
    '/assets/css/personal-agent-ai-credits.css?v=1.0.0',
    '/assets/css/personal-agent-contact-intelligence.css?v=1.0.0',
    '/assets/css/agent-header-tabs-shared.css?v=1.0.0',
    '/assets/css/multi-agent-workspace.css?v=1.0.0',
    '/assets/css/multi-agent-workspace-state.css?v=1.0.0',
    '/assets/css/multi-agent-runtime.css?v=1.1.0',
];
$page_scripts = [
    '/assets/js/agent-workspace.js',
    '/assets/js/personal-gifting-agent.js',
    '/assets/js/personal-gifting-agent-render.js',
    '/assets/js/personal-gifting-agent-actions.js',
    '/assets/js/personal-gifting-workflows.js',
    '/assets/js/personal-agent-chat-canvas.js?v=1.2.0',
    '/assets/js/personal-agent-chat-history.js?v=1.2.0',
    '/assets/js/agent-merchant-handoff.js?v=1.0.0',
    '/assets/js/personal-agent-marketplace-cards.js?v=1.1.0',
    '/assets/js/personal-agent-opportunity-actions.js?v=1.1.0',
    '/assets/js/personal-agent-attribution-runtime.js?v=1.0.0',
    '/assets/js/personal-agent-recovery.js?v=1.0.0',
    '/assets/js/personal-agent-gift-results.js?v=1.0.0&image=1.1.0',
    '/assets/js/personal-agent-design-studio.js?v=1.3.0',
    '/assets/js/personal-agent-design-studio-social.js?v=1.0.0',
    '/assets/js/personal-agent-design-studio-calendar.js?v=1.0.0',
    '/assets/js/personal-agent-ai-credits.js?v=1.0.0',
    '/assets/js/personal-agent-contact-intelligence.js?v=1.0.1',
    '/assets/js/multi-agent-workspace.js?v=1.0.0',
    '/assets/js/multi-agent-runtime.js?v=1.0.0',
];

require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/agent-workspace.php';
require __DIR__ . '/includes/footer.php';