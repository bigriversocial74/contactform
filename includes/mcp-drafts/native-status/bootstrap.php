<?php
declare(strict_types=1);
const MG_MCP_NATIVE_STATE_CLASSES = ['not_created','draft','review','active','completed','archived','missing','unknown'];
function mg_mcp_native_status_safe_url(mixed $value): ?string
{
    $url = trim((string)$value);
    return $url !== '' && str_starts_with($url, '/') && !str_starts_with($url, '//') ? $url : null;
}
function mg_mcp_native_status_json(mixed $value): array
{
    return mg_mcp_draft_json($value);
}
