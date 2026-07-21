<?php
declare(strict_types=1);
function mg_mcp_native_status_resolve(PDO $pdo, array $row): array
{
    return match ((string)($row['conversion_type'] ?? '')) {
        'gift_draft'=>mg_mcp_native_status_resolve_gift($pdo, $row),
        'campaign_draft'=>mg_mcp_native_status_resolve_campaign($pdo, $row),
        'reward_template_draft'=>mg_mcp_native_status_resolve_template($pdo, $row),
        'message_draft'=>mg_mcp_native_status_resolve_message($pdo, $row),
        default=>['state'=>'unknown','class'=>'unknown','updated_at'=>null,'details'=>[]],
    };
}
