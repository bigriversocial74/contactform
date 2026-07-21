<?php
declare(strict_types=1);
require_once __DIR__ . '/mcp-oauth/bootstrap.php';
require_once __DIR__ . '/mcp-oauth/clients.php';
require_once __DIR__ . '/mcp-oauth/consent.php';
require_once __DIR__ . '/mcp-oauth/tokens.php';
require_once __DIR__ . '/mcp-oauth/connections.php';
require_once __DIR__ . '/mcp-oauth/endpoints.php';
if (PHP_SAPI !== 'cli' && basename((string)($_SERVER['SCRIPT_NAME'] ?? '')) === 'account-ai-connections.php') {
    if ((string)($_GET['action'] ?? '') === 'manage') {
        require __DIR__ . '/mcp-oauth/account-connection-settings.php';
    } else {
        require __DIR__ . '/mcp-oauth/account-connections-page.php';
    }
    exit;
}
