<?php
declare(strict_types=1);
function mg_mcp_native_status_resolve_gift(PDO $pdo, array $row): array
{
    $stmt = $pdo->prepare('SELECT status,visibility,published_at,updated_at FROM gifts WHERE public_id=? AND sender_user_id=? LIMIT 1');
    $stmt->execute([(string)$row['native_public_id'], (int)$row['owner_user_id']]);
    $native = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$native) return ['state'=>'missing','class'=>'missing','updated_at'=>null,'details'=>[]];
    $details = ['visibility'=>(string)($native['visibility'] ?? ''),'published_at'=>$native['published_at'] ?? null];
    $state = strtolower((string)($native['status'] ?? 'unknown'));
    $class = (string)$details['visibility'] === 'published' ? 'active' : ($state === 'draft' ? 'draft' : 'unknown');
    return ['state'=>$state,'class'=>$class,'updated_at'=>$native['updated_at'] ?? null,'details'=>$details];
}
