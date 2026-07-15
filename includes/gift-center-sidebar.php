<?php
declare(strict_types=1);

/*
 * Inbox, Sent, and Claimed keep the established universal customer sidebar.
 * My Lists is inserted directly into the rendered navigation so the entry is
 * visible regardless of the app-sidebar template's attributes or class order.
 */
$use_inbox_sidebar = true;
ob_start();
require __DIR__ . '/agent-sidebar.php';
$sidebarHtml = (string) ob_get_clean();

$myListsItem = '<span class="mg-side-nav-section mg-gift-center-lists-section">Personal Gifting</span>'
    . '<a class="mg-gift-center-my-lists" href="/lists.php"><strong>My Lists</strong><span>People, occasions, and gifting groups</span></a>';

$navStart = strpos($sidebarHtml, '<nav ');
$navOpenEnd = $navStart === false ? false : strpos($sidebarHtml, '>', $navStart);
if ($navOpenEnd !== false) {
    $sidebarHtml = substr($sidebarHtml, 0, $navOpenEnd + 1)
        . $myListsItem
        . substr($sidebarHtml, $navOpenEnd + 1);
}

echo $sidebarHtml;
