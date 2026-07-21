<?php
declare(strict_types=1);
function mg_mcp_native_status_attach_connection_drafts(PDO $pdo, array $context, array $drafts): array
{
    foreach ($drafts as $index=>$draft) {
        if (!is_array($draft) || empty($draft['id'])) continue;
        $drafts[$index]['handoff'] = mg_mcp_native_status_for_connection($pdo, $context, (string)$draft['id']);
    }
    return $drafts;
}
