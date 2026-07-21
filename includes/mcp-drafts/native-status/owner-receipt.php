<?php
declare(strict_types=1);
function mg_mcp_native_status_latest_receipt(PDO $pdo, string $conversionPublicId, int $ownerUserId): ?array
{
    $stmt = $pdo->prepare("SELECT id,payload_json,created_at FROM events WHERE event_type=? AND user_id=? AND JSON_UNQUOTE(JSON_EXTRACT(payload_json,'$.conversion_id'))=? ORDER BY id DESC LIMIT 1");
    $stmt->execute([mg_mcp_native_status_event_type(), $ownerUserId, $conversionPublicId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}
