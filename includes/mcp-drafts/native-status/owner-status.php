<?php
declare(strict_types=1);
function mg_mcp_native_status_for_owner(PDO $pdo, array $user, string $conversionPublicId): array
{
    $ownerUserId = (int)($user['id'] ?? 0);
    if ($ownerUserId < 1) throw new MgMcpDraftException('Authentication is required.', 401, 'MCP_CONVERSION_AUTH_REQUIRED');
    $row = mg_mcp_native_status_row_for_owner($pdo, $ownerUserId, $conversionPublicId);
    return mg_mcp_native_status_observe_row($pdo, $row, 'owner', null, $ownerUserId);
}
