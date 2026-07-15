<?php
declare(strict_types=1);

/*
 * Inbox, Sent, and Claimed keep the established universal customer sidebar,
 * with My Lists promoted as the dedicated entry point for list management.
 */
ob_start();
require __DIR__ . '/agent-sidebar.php';
$sidebarHtml = (string) ob_get_clean();

$myListsItem = '<span class="mg-side-nav-section">Personal Gifting</span>'
    . '<a href="/lists.php"><strong>My Lists</strong><span>People, occasions, and gifting groups</span></a>';

$sidebarHtml = preg_replace(
    '/(<nav class="mg-app-side-nav mg-universal-side-nav"[^>]*>)/',
    '$1' . $myListsItem,
    $sidebarHtml,
    1
) ?? $sidebarHtml;

echo $sidebarHtml;
