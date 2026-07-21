<?php
declare(strict_types=1);
function mg_mcp_native_status_list_for_owner(PDO $pdo, int $ownerUserId): array
{
    $stmt = $pdo->prepare(mg_mcp_native_status_conversion_sql("WHERE cv.owner_user_id=? AND cv.status IN ('created','opened') ORDER BY cv.id DESC LIMIT 100"));
    $stmt->execute([$ownerUserId]);
    $items = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $receipt = mg_mcp_native_status_latest_receipt($pdo, (string)$row['public_id'], $ownerUserId);
        $payload = $receipt ? mg_mcp_native_status_event_payload($receipt) : [];
        $resolved = $receipt ? ['state'=>(string)($payload['native_state'] ?? 'unknown'),'class'=>(string)($payload['state_class'] ?? 'unknown'),'updated_at'=>$payload['native_updated_at'] ?? null,'details'=>is_array($payload['details'] ?? null) ? $payload['details'] : []] : null;
        $item = mg_mcp_native_status_projection($row, $resolved, $receipt, false);
        $item['title'] = (string)$row['title'];
        $item['summary'] = (string)$row['summary'];
        $items[] = $item;
    }
    return $items;
}
