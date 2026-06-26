<?php
declare(strict_types=1);

$agentSidebarActive = (string) ($agent_tab ?? basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''), '.php'));
$appSidebarVariant = 'utility';
$appSidebarLabel = 'Workspace';
$appSidebarActive = $agentSidebarActive;
$appSidebarCompact = true;
$appSidebarBeforeNav = '';
$appSidebarAfterNav = '';
$appSidebarFooter = '';
$appSidebarNav = [
    'agent' => [
        'section' => 'Workspace',
        'label' => 'Agent',
        'detail' => 'Build and manage gifting agents',
        'href' => '/agent.php',
        'visible' => true,
        'active' => $agentSidebarActive === 'agent',
    ],
    'inbox' => [
        'label' => 'Inbox',
        'detail' => 'Received and redeemable gifts',
        'href' => '/inbox.php',
        'visible' => true,
        'active' => $agentSidebarActive === 'inbox',
    ],
    'sent' => [
        'label' => 'Sent',
        'detail' => 'Outbound gifts and activity',
        'href' => '/sent.php',
        'visible' => true,
        'active' => $agentSidebarActive === 'sent',
    ],
    'claimed' => [
        'label' => 'Claimed',
        'detail' => 'Redeemed gifts and history',
        'href' => '/claimed.php',
        'visible' => true,
        'active' => $agentSidebarActive === 'claimed',
    ],
    'messages' => [
        'section' => 'Account',
        'label' => 'Messages',
        'detail' => 'Gift conversations',
        'href' => '/messages.php',
        'visible' => true,
    ],
    'merchant' => [
        'section' => 'Merchant',
        'label' => 'Merchant Workspace',
        'detail' => 'Products, campaigns, claims',
        'href' => '/merchant.php',
        'visible' => true,
    ],
    'build' => [
        'label' => 'Create Gift',
        'detail' => 'Open the builder',
        'href' => '/build.php',
        'visible' => true,
    ],
];

require __DIR__ . '/app-sidebar.php';
