<?php
declare(strict_types=1);

require_once __DIR__ . '/_mcp_connections.php';

mg_require_method('POST');
$actor = mg_admin_mcp_require_user();
$actorId = (int)$actor['id'];
mg_rate_limit('admin.mcp_connection.create', 'user:' . $actorId, 30, 60);
$input = mg_input();
mg_require_csrf_for_write($input);
$pdo = mg_db();

try {
    $connection = mg_admin_mcp_provision($pdo, $actor, $input);
} catch (MgAdminMcpProvisioningException $error) {
    mg_security_log('warning', 'admin.mcp_connection.provision_rejected', 'MCP connection provisioning was rejected.', [
        'reason' => $error->getMessage(),
    ], $actorId);
    mg_fail($error->getMessage(), $error->httpStatus());
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    mg_security_log('error', 'admin.mcp_connection.provision_failed', 'MCP connection provisioning failed.', [
        'exception_class' => $error::class,
    ], $actorId);
    mg_fail('Unable to provision MCP connection.', 500);
}

header('Cache-Control: private, no-store, max-age=0');
header('Vary: Cookie, Authorization');
mg_ok(['connection' => $connection], 'MCP connection provisioned.');
