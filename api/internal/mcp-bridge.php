<?php
declare(strict_types=1);

require_once __DIR__ . '/_mcp_bridge.php';

mg_require_method('POST');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');

$rawBody = file_get_contents('php://input');
$rawBody = is_string($rawBody) ? $rawBody : '';

try {
    $payload = mg_mcp_bridge_json_decode($rawBody);
    $pdo = mg_db();
    $context = mg_mcp_bridge_authenticate($pdo, $rawBody, $payload);
    $operation = mg_mcp_bridge_text($payload['operation'] ?? '', 120, 'operation');
    $arguments = isset($payload['arguments']) && is_array($payload['arguments']) ? $payload['arguments'] : [];

    $data = match ($operation) {
        'connection.resolve' => (function () use ($context): array {
            mg_mcp_bridge_require_scope($context, 'profile:read');
            return mg_mcp_bridge_connection_projection($context);
        })(),
        'catalog.search' => (function () use ($pdo, $context, $arguments): array {
            mg_mcp_bridge_require_scope($context, 'catalog:read');
            return mg_mcp_bridge_catalog_search($pdo, $arguments, (int)$context['user_id']);
        })(),
        'catalog.get_item' => (function () use ($pdo, $context, $arguments): array {
            mg_mcp_bridge_require_scope($context, 'catalog:read');
            return mg_mcp_bridge_catalog_item($pdo, $arguments);
        })(),
        'receipt.record' => mg_mcp_bridge_record_receipt($pdo, $context, $arguments),
        default => throw new MgMcpBridgeException('Bridge operation is not allowed.', 404, 'MCP_BRIDGE_OPERATION_UNKNOWN'),
    };

    $touch = $pdo->prepare('UPDATE mcp_connections SET last_activity_at=NOW(),updated_at=updated_at WHERE id=?');
    $touch->execute([(int)$context['connection_db_id']]);

    mg_json([
        'ok' => true,
        'request_id' => mg_mcp_bridge_text($payload['request_id'] ?? mg_mcp_bridge_uuid(), 36, 'request_id'),
        'data' => $data,
    ]);
} catch (MgMcpBridgeException $error) {
    mg_security_log('warning', 'mcp.bridge.denied', 'MCP bridge request denied.', [
        'bridge_code' => $error->bridgeCode(),
        'http_status' => $error->httpStatus(),
    ]);
    mg_json([
        'ok' => false,
        'error' => [
            'code' => $error->bridgeCode(),
            'message' => $error->getMessage(),
        ],
    ], $error->httpStatus());
} catch (Throwable $error) {
    mg_security_log('error', 'mcp.bridge.failed', 'MCP bridge request failed.', [
        'exception_class' => $error::class,
        'exception_message' => mb_substr($error->getMessage(), 0, 500),
    ]);
    mg_json([
        'ok' => false,
        'error' => [
            'code' => 'MCP_BRIDGE_INTERNAL_ERROR',
            'message' => 'The canonical bridge could not complete the request.',
        ],
    ], 500);
}
