<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit('Not found.'); }
$root = dirname(__DIR__);
$required = [
 '.well-known/oauth-authorization-server/index.php','oauth/authorize.php','oauth/token.php','oauth/revoke.php','oauth/register.php',
 'account-ai-connections.php','admin/mcp-oauth-clients.php','api/internal/_mcp_oauth_bridge.php',
 'database/20260720_mcp_external_agent_authorization_phase2a_v1.sql','deploy/vps/php-oauth.env.example',
 'services/mcp/src/auth/externalOAuth.ts','services/mcp/src/bridge/oauthBridge.ts','services/mcp/tests/externalOAuth.test.mjs'
];
foreach ($required as $file) { if (!is_file($root.'/'.$file)) throw new RuntimeException('Missing Phase 2A file: '.$file); }
$manifest = require $root.'/config/migrations.php';
$foundation = array_search('20260720_microgifter_mcp_automation_foundation_v1.sql', $manifest['ordered_files'], true);
$phase2a = array_search('20260720_mcp_external_agent_authorization_phase2a_v1.sql', $manifest['ordered_files'], true);
if (!is_int($foundation) || !is_int($phase2a) || $phase2a !== $foundation + 1) throw new RuntimeException('Phase 2A migration order is invalid.');
$sql = file_get_contents($root.'/database/20260720_mcp_external_agent_authorization_phase2a_v1.sql');
foreach (['mcp_oauth_client_registrations','mcp_oauth_authorization_requests','mcp_oauth_consents','mcp_oauth_authorization_codes','mcp_oauth_tokens','token_hash CHAR(64)',"token_type ENUM('access','refresh')"] as $needle) {
 if (!is_string($sql) || !str_contains($sql,$needle)) throw new RuntimeException('Migration contract missing: '.$needle);
}
if (preg_match('/\b(access_token|refresh_token|authorization_code)\s+(VARCHAR|TEXT|CHAR)\b/i',(string)$sql)===1) throw new RuntimeException('Raw credential storage detected.');
$server=file_get_contents($root.'/services/mcp/src/server.ts');
foreach(['HttpOAuthTokenResolver','externalOAuth.enabled','listenInternalMcp'] as $needle){if(!is_string($server)||!str_contains($server,$needle))throw new RuntimeException('Node wiring missing: '.$needle);}
$nginx=file_get_contents($root.'/deploy/vps/nginx/mcp.microgifter.com.conf.template');
if(!is_string($nginx)||!str_contains($nginx,'/.well-known/oauth-protected-resource'))throw new RuntimeException('Nginx OAuth discovery route missing.');
$phpEnv=file_get_contents($root.'/deploy/vps/php-oauth.env.example');
$nodeEnv=file_get_contents($root.'/deploy/vps/mcp.env.example');
foreach(['MG_MCP_OAUTH_ENABLED','MG_MCP_OAUTH_ISSUER','MG_MCP_OAUTH_RESOURCE_URI'] as $needle){if(!is_string($phpEnv)||!str_contains($phpEnv,$needle))throw new RuntimeException('PHP OAuth environment missing: '.$needle);}
foreach(['MICROGIFTER_MCP_EXTERNAL_OAUTH_ENABLED','MICROGIFTER_MCP_AUTHORIZATION_SERVER','MICROGIFTER_MCP_RESOURCE_URI'] as $needle){if(!is_string($nodeEnv)||!str_contains($nodeEnv,$needle))throw new RuntimeException('Node OAuth environment missing: '.$needle);}
echo "MCP external agent authorization Phase 2A static contract passed.\n";
