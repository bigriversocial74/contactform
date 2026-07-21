<?php
declare(strict_types=1);
function mg_mcp_native_status_resolve_campaign(PDO $pdo, array $row): array
{
    $merchantId = (int)($row['workspace_merchant_user_id'] ?? 0);
    $stmt = $pdo->prepare("SELECT event_type,event_context_json,created_at FROM campaign_events WHERE merchant_user_id=? AND JSON_UNQUOTE(JSON_EXTRACT(event_context_json,'$.draft_id'))=? AND event_type IN ('crm.campaign_builder.draft','crm.campaign_builder.launched') ORDER BY id DESC LIMIT 1");
    $stmt->execute([$merchantId, (string)$row['native_public_id']]);
    $native = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$native) return ['state'=>'missing','class'=>'missing','updated_at'=>null,'details'=>[]];
    $context = mg_mcp_native_status_json($native['event_context_json'] ?? null);
    $state = strtolower(trim((string)($context['status'] ?? '')));
    if ($state === '') $state = (string)$native['event_type'] === 'crm.campaign_builder.launched' ? 'launched' : 'draft';
    $details = ['event_type'=>(string)$native['event_type']];
    $class = $state === 'launched' ? 'active' : ($state === 'draft' ? 'draft' : 'unknown');
    return ['state'=>$state,'class'=>$class,'updated_at'=>$native['created_at'] ?? null,'details'=>$details];
}
