<?php
declare(strict_types=1);

/*
 * Inbox, Sent, and Claimed share the same customer navigation and private
 * Personal Agent chat history used by agent.php. This removes the former
 * duplicate My Lists injection and keeps one sidebar authority.
 */
require __DIR__ . '/personal-agent-sidebar.php';
