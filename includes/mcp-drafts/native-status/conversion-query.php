<?php
declare(strict_types=1);
function mg_mcp_native_status_conversion_sql(string $where): string
{
    return "SELECT cv.*,d.public_id AS draft_public_id,d.draft_type,d.owner_user_id,d.connection_id,d.workspace_type,d.workspace_id,d.title,d.summary,mw.merchant_user_id AS workspace_merchant_user_id FROM mcp_agent_drafts d LEFT JOIN mcp_agent_draft_conversions cv ON cv.draft_id=d.id LEFT JOIN merchant_workspaces mw ON mw.id=d.workspace_id $where";
}
function mg_mcp_native_status_row_for_connection(PDO $pdo, array $context, string $draftPublicId): array
{
    $draftPublicId = mg_mcp_draft_uuid($draftPublicId, 'draft');
    $stmt = $pdo->prepare(mg_mcp_native_status_conversion_sql('WHERE d.public_id=? AND d.connection_id=? LIMIT 1'));
    $stmt->execute([$draftPublicId, (int)$context['connection_db_id']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new MgMcpDraftException('Draft not found.', 404, 'MCP_DRAFT_NOT_FOUND');
    mg_mcp_draft_require_context($context, (string)$row['draft_type']);
    return $row;
}
function mg_mcp_native_status_row_for_owner(PDO $pdo, int $ownerUserId, string $conversionPublicId): array
{
    $conversionPublicId = mg_mcp_draft_uuid($conversionPublicId, 'conversion');
    $stmt = $pdo->prepare(mg_mcp_native_status_conversion_sql('WHERE cv.public_id=? AND cv.owner_user_id=? LIMIT 1'));
    $stmt->execute([$conversionPublicId, $ownerUserId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row || empty($row['id'])) throw new MgMcpDraftException('Conversion not found.', 404, 'MCP_CONVERSION_NOT_FOUND');
    return $row;
}
