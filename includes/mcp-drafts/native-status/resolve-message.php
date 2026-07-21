<?php
declare(strict_types=1);
function mg_mcp_native_status_message_state(string $eventType): string
{
    return match ($eventType) {
        'crm.agent.message.draft.created'=>'draft',
        'crm.agent.message.draft.edited'=>'edited',
        'crm.agent.message.draft.approved'=>'approved',
        'crm.agent.message.sent'=>'sent',
        'crm.agent.message.discarded'=>'discarded',
        'crm.agent.message.followup_created'=>'followup_created',
        default=>'unknown',
    };
}
function mg_mcp_native_status_resolve_message(PDO $pdo, array $row): array
{
    $stmt = $pdo->prepare("SELECT event_type,created_at FROM campaign_events WHERE merchant_user_id=? AND JSON_UNQUOTE(JSON_EXTRACT(event_context_json,'$.message_draft_id'))=? AND event_type IN ('crm.agent.message.draft.created','crm.agent.message.draft.edited','crm.agent.message.draft.approved','crm.agent.message.sent','crm.agent.message.discarded','crm.agent.message.followup_created') ORDER BY id DESC LIMIT 1");
    $stmt->execute([(int)($row['workspace_merchant_user_id'] ?? 0), (string)$row['native_public_id']]);
    $native = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$native) return ['state'=>'missing','class'=>'missing','updated_at'=>null,'details'=>[]];
    $state = mg_mcp_native_status_message_state((string)$native['event_type']);
    $details = ['event_type'=>(string)$native['event_type']];
    $class = match ($state) { 'draft','edited'=>'draft', 'approved'=>'review', 'sent','followup_created'=>'completed', 'discarded'=>'archived', default=>'unknown' };
    return ['state'=>$state,'class'=>$class,'updated_at'=>$native['created_at'] ?? null,'details'=>$details];
}
