<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/mcp-oauth.php';
mg_mcp_oauth_dispatch_public_endpoint(basename(__FILE__, '.php'));
