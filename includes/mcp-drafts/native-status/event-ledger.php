<?php
declare(strict_types=1);
function mg_mcp_native_status_event_type(): string
{
    return 'mcp.agent_draft.native_status.changed';
}
function mg_mcp_native_status_event_payload(array $event): array
{
    return mg_mcp_native_status_json($event['payload_json'] ?? null);
}
function mg_mcp_native_status_record(PDO $pdo, array $row, array $resolved, string $observerType, ?int $connectionId, ?int $actorUserId): array
{
    $fingerprint = hash('sha256', json_encode(mg_mcp_draft_canonicalize([
        'state'=>$resolved['state'],'class'=>$resolved['class'],'updated_at'=>$resolved['updated_at'],'details'=>$resolved['details'],
    ]), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    $conversionPublicId = (string)($row['public_id'] ?? '');
    $ownerUserId = (int)($row['owner_user_id'] ?? 0);
    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) $pdo->beginTransaction();
    try {
        $lock = $pdo->prepare('SELECT id FROM mcp_agent_draft_conversions WHERE id=? FOR UPDATE');
        $lock->execute([(int)$row['id']]);
        if (!$lock->fetchColumn()) throw new MgMcpDraftException('Conversion not found.', 404, 'MCP_CONVERSION_NOT_FOUND');
        $latestStmt = $pdo->prepare("SELECT id,payload_json,created_at FROM events WHERE event_type=? AND user_id=? AND JSON_UNQUOTE(JSON_EXTRACT(payload_json,'$.conversion_id'))=? ORDER BY id DESC LIMIT 1");
        $latestStmt->execute([mg_mcp_native_status_event_type(), $ownerUserId, $conversionPublicId]);
        $latest = $latestStmt->fetch(PDO::FETCH_ASSOC) ?: null;
        $latestPayload = $latest ? mg_mcp_native_status_event_payload($latest) : [];
        $changed = !is_string($latestPayload['state_fingerprint'] ?? null) || !hash_equals((string)$latestPayload['state_fingerprint'], $fingerprint);
        if ($changed) {
            $payload = [
                'conversion_id'=>$conversionPublicId,
                'draft_id'=>(string)($row['draft_public_id'] ?? ''),
                'conversion_type'=>(string)($row['conversion_type'] ?? ''),
                'observer_type'=>$observerType,
                'observer_connection_id'=>$connectionId,
                'actor_user_id'=>$actorUserId,
                'native_state'=>(string)$resolved['state'],
                'state_class'=>(string)$resolved['class'],
                'native_updated_at'=>$resolved['updated_at'],
                'state_fingerprint'=>$fingerprint,
                'details'=>(array)$resolved['details'],
                'execution_enabled'=>false,
            ];
            $insert = $pdo->prepare('INSERT INTO events (event_type,user_id,payload_json,created_at) VALUES (?,?,?,NOW())');
            $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $insert->execute([mg_mcp_native_status_event_type(), $ownerUserId, $encoded]);
            $latest = ['id'=>(int)$pdo->lastInsertId(),'payload_json'=>$encoded,'created_at'=>gmdate('Y-m-d H:i:s')];
        }
        if ($ownsTransaction) $pdo->commit();
        return ['changed'=>$changed,'receipt'=>$latest ?: []];
    } catch (Throwable $error) {
        if ($ownsTransaction && $pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}
