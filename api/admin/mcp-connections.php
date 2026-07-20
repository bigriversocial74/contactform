<?php
declare(strict_types=1);

require_once __DIR__ . '/_mcp_connections.php';

mg_require_method('GET');
$actor = mg_admin_mcp_require_user();
$actorId = (int)$actor['id'];
mg_rate_limit('admin.mcp_connections.read', 'user:' . $actorId, 180, 60);

try {
    $data = mg_admin_mcp_read(mg_db());
} catch (Throwable $error) {
    mg_security_log('error', 'admin.mcp_connections.read_failed', 'MCP operations console failed to load.', [
        'exception_class' => $error::class,
    ], $actorId);
    mg_fail('Unable to load MCP operations.', 500);
}

header('Cache-Control: private, no-store, max-age=0');
header('Vary: Cookie, Authorization');
mg_ok($data, 'MCP operations loaded.');
