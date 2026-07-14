<?php
declare(strict_types=1);

$user = mg_current_user();
$active = (string) ($agent_personal_view ?? 'home');
$appSidebarVariant = 'utility';
$appSidebarLabel = 'Personal Agent';
$appSidebarActive = $active;
$appSidebarCompact = true;
$appSidebarBeforeNav = '';
$appSidebarAfterNav = '';
$appSidebarFooter = '';

$appSidebarNav = [
    'home' => [
        'section' => 'Personal Gifting Agent',
        'label' => 'Home',
        'detail' => 'Upcoming brief and suggestions',
        'href' => '/agent.php',
        'visible' => true,
        'active' => $active === 'home',
    ],
    'lists' => [
        'label' => 'Lists',
        'detail' => 'Family, friends, teams, and groups',
        'href' => '/lists.php',
        'visible' => true,
        'active' => false,
    ],
    'contacts' => [
        'label' => 'Contacts',
        'detail' => 'Relationship and gifting context',
        'href' => '/agent.php?view=contacts',
        'visible' => true,
        'active' => $active === 'contacts',
    ],
    'birthdays' => [
        'label' => 'Birthdays',
        'detail' => 'Upcoming birthday planning',
        'href' => '/agent.php?view=birthdays',
        'visible' => true,
        'active' => $active === 'birthdays',
    ],
    'calendar' => [
        'label' => 'Gift Calendar',
        'detail' => 'Important dates and occasions',
        'href' => '/agent.php?view=calendar',
        'visible' => true,
        'active' => $active === 'calendar',
    ],
    'plans' => [
        'section' => 'Planning',
        'label' => 'Draft Plans',
        'detail' => 'Approval-first gifting plans',
        'href' => '/agent.php?view=plans',
        'visible' => true,
        'active' => $active === 'plans',
    ],
    'reminders' => [
        'label' => 'Reminders',
        'detail' => 'Due dates and follow-ups',
        'href' => '/agent.php?view=reminders',
        'visible' => true,
        'active' => $active === 'reminders',
    ],
    'group' => [
        'label' => 'Group Gifting',
        'detail' => 'List-based shared draft plans',
        'href' => '/agent.php?view=group',
        'visible' => true,
        'active' => $active === 'group',
    ],
    'memory' => [
        'section' => 'Agent',
        'label' => 'Agent Memory',
        'detail' => 'Reusable preferences and rules',
        'href' => '/agent.php?view=memory',
        'visible' => true,
        'active' => $active === 'memory',
    ],
    'settings' => [
        'label' => 'Settings',
        'detail' => 'Model, budget, and suggestion defaults',
        'href' => '/agent.php?view=settings',
        'visible' => true,
        'active' => $active === 'settings',
    ],
    'inbox' => [
        'section' => 'Account',
        'label' => 'Inbox',
        'detail' => 'Received Microgifts',
        'href' => '/inbox.php',
        'visible' => true,
        'active' => false,
    ],
    'sent' => [
        'label' => 'Sent',
        'detail' => 'Outbound gifts',
        'href' => '/sent.php',
        'visible' => true,
        'active' => false,
    ],
    'claimed' => [
        'label' => 'Claimed',
        'detail' => 'Claimed gifts and rewards',
        'href' => '/claimed.php',
        'visible' => true,
        'active' => false,
    ],
];

require __DIR__ . '/app-sidebar.php';
