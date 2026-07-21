<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/mcp-oauth.php';

function mg_mcp_oauth_bridge_authenticate(PDO $pdo, string $rawBody, array $payload): array
{
    if (!mg_mcp_bridge_enabled()) {
        throw new MgMcpBridgeException('Bridge is disabled.', 404, 'MCP_BRIDGE_DISABLED');
    }
    if (!mg_mcp_oauth_enabled()) {
        throw new MgMcpBridgeException('External OAuth is disabled.', 404, 'MCP_OAUTH_DISABLED');
    }
    $secret = mg_mcp_bridge_secret();
    if (strlen($secret) < 32) {
        throw new MgMcpBridgeException('Bridge is unavailable.', 503, 'MCP_BRIDGE_SECRET_INVALID');
    }

    $timestamp = mg_mcp_bridge_header('X-Microgifter-MCP-Timestamp');
    $nonce = mg_mcp_bridge_header('X-Microgifter-MCP-Nonce');
    $signature = strtolower(mg_mcp_bridge_header('X-Microgifter-MCP-Signature'));
    if (preg_match('/^[0-9]{10}$/', $timestamp) !== 1 || abs(time() - (int)$timestamp) > 300) {
        throw new MgMcpBridgeException('Bridge request timestamp is invalid.', 401, 'MCP_BRIDGE_TIMESTAMP_INVALID');
    }
    if (preg_match('/^[A-Za-z0-9_-]{16,128}$/', $nonce) !== 1 || preg_match('/^[a-f0-9]{64}$/', $signature) !== 1) {
        throw new MgMcpBridgeException('Bridge authentication is invalid.', 401, 'MCP_BRIDGE_AUTH_INVALID');
    }
    $expected = mg_mcp_bridge_expected_signature($secret, $timestamp, $nonce, $rawBody);
    if (!hash_equals($expected, $signature)) {
        throw new MgMcpBridgeException('Bridge authentication is invalid.', 401, 'MCP_BRIDGE_AUTH_INVALID');
    }

    $arguments = isset($payload['arguments']) && is_array($payload['arguments']) ? $payload['arguments'] : [];
    $tokenHash = strtolower(trim((string)($arguments['token_sha256'] ?? '')));
    $resourceUri = trim((string)($arguments['resource_uri'] ?? ''));
    try {
        $resolved = mg_mcp_oauth_resolve_access_token_hash($pdo, $tokenHash, $resourceUri);
    } catch (MgMcpOAuthException $error) {
        throw new MgMcpBridgeException(
            $error->getMessage(),
            $error->httpStatus(),
            $error->oauthError() === 'invalid_token' ? 'MCP_OAUTH_TOKEN_INVALID' : 'MCP_OAUTH_TOKEN_DENIED'
        );
    }

    $context = mg_mcp_draft_bridge_connection($pdo, (string)$resolved['connection_public_id']);
    $tokenScopes = array_values(array_map('strval', (array)$resolved['token_scopes']));
    $context['scopes'] = array_values(array_intersect((array)$context['scopes'], $tokenScopes));
    if (!in_array('profile:read', $context['scopes'], true)) {
        throw new MgMcpBridgeException('OAuth access token lacks required scope.', 403, 'MCP_OAUTH_SCOPE_DENIED');
    }

    mg_mcp_bridge_reserve_nonce($pdo, $context, $nonce, hash('sha256', $rawBody));
    return [
        'context' => $context,
        'data' => [
            'connection' => mg_mcp_draft_bridge_projection($context),
            'oauthClientId' => (string)$resolved['oauth_client_id'],
            'tokenFamilyId' => (string)$resolved['token_family_id'],
        ],
    ];
}
