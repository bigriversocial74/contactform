<?php
declare(strict_types=1);
function mg_mcp_native_status_for_connection(PDO $pdo, array $context, string $draftPublicId): array
{
    $row = mg_mcp_native_status_row_for_connection($pdo, $context, $draftPublicId);
    return mg_mcp_native_status_observe_row($pdo, $row, 'client', (int)$context['connection_db_id'], null);
}
