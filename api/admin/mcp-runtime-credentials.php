<?php
declare(strict_types=1);

require_once __DIR__ . '/_mcp_connections.php';

mg_require_method('POST');
$actor = mg_admin_mcp_require_user();
$actorId = (int)$actor['id'];
mg_rate_limit('admin.mcp_runtime_credentials.generate', 'user:' . $actorId, 10, 60);
$input = mg_input();
mg_require_csrf_for_write($input);

try {
    $bridgeUrl = trim((string)($input['bridge_url'] ?? 'https://microgifter.com/api/internal/mcp-bridge.php'));
    $parts = parse_url($bridgeUrl);
    if (strlen($bridgeUrl) > 500
        || preg_match('/[\x00-\x20\x7f]/', $bridgeUrl) === 1
        || filter_var($bridgeUrl, FILTER_VALIDATE_URL) === false
        || !is_array($parts)
        || strtolower((string)($parts['scheme'] ?? '')) !== 'https'
        || empty($parts['host'])
        || isset($parts['user'])
        || isset($parts['pass'])
        || isset($parts['fragment'])) {
        throw new MgAdminMcpProvisioningException('Bridge URL must use HTTPS without embedded credentials or fragments.');
    }
    $input['bridge_url'] = $bridgeUrl;
    $credentials = mg_admin_mcp_runtime_credentials(mg_db(), $actor, $input);
} catch (MgAdminMcpProvisioningException $error) {
    mg_security_log('warning', 'admin.mcp_runtime_credentials.rejected', 'MCP runtime credential generation was rejected.', [
        'reason' => $error->getMessage(),
    ], $actorId);
    mg_fail($error->getMessage(), $error->httpStatus());
} catch (Throwable $error) {
    mg_security_log('error', 'admin.mcp_runtime_credentials.failed', 'MCP runtime credential generation failed.', [
        'exception_class' => $error::class,
    ], $actorId);
    mg_fail('Unable to generate MCP runtime credentials.', 500);
}

header('Cache-Control: private, no-store, max-age=0');
header('Pragma: no-cache');
header('Vary: Cookie, Authorization');
mg_ok(['credentials' => $credentials], 'One-time MCP runtime credentials generated.');
