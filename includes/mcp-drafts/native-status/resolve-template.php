<?php
declare(strict_types=1);
function mg_mcp_native_status_resolve_template(PDO $pdo, array $row): array
{
    $table = 'reward' . '_templates';
    $stmt = $pdo->prepare('SELECT status,updated_at FROM ' . $table . ' WHERE public_id=? AND merchant_user_id=? LIMIT 1');
    $stmt->execute([(string)$row['native_public_id'], (int)($row['workspace_merchant_user_id'] ?? 0)]);
    $native = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$native) return ['state'=>'missing','class'=>'missing','updated_at'=>null,'details'=>[]];
    $state = strtolower((string)$native['status']);
    $class = match ($state) { 'draft'=>'draft', 'active'=>'active', 'paused'=>'review', 'archived'=>'archived', default=>'unknown' };
    return ['state'=>$state,'class'=>$class,'updated_at'=>$native['updated_at'] ?? null,'details'=>[]];
}
