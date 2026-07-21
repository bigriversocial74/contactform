<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/mcp-oauth.php';

mg_require_method('GET');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: public, max-age=300');
header('X-Robots-Tag: noindex, nofollow');

try {
    mg_mcp_oauth_require_enabled();
    mg_json(mg_mcp_oauth_authorization_server_metadata(mg_db()));
} catch (MgMcpOAuthException $error) {
    mg_mcp_oauth_json_error($error);
} catch (Throwable $error) {
    mg_fail_unexpected($error, 'mcp.oauth.metadata_failed', 'OAuth metadata is unavailable.');
}
