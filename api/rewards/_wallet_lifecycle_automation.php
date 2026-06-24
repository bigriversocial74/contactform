<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/communications/_communications.php';
require_once dirname(__DIR__) . '/public/campaigns/_followups.php';
require_once dirname(__DIR__, 2) . '/includes/merchant-crm.php';

function mg_wallet_lifecycle_contact(PDO $pdo, array $item): array
{
    if (empty($item['contact_id'])) return [];
    $stmt = $pdo->prepare('SELECT cc.public_id,cc.email,cc.phone,cc.name,c.campaign_type,c.title campaign_title FROM campaign_contacts cc LEFT JOIN campaigns c ON c.id=cc.campaign_id WHERE cc.id=? LIMIT 1');
    $stmt->execute([(int)$item['contact_id']]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}
function mg_wallet_lifecycle_followup(PDO $pdo, array $item, string $eventType, array $context=[]): array
{
    if (empty($item['campaign_id']) || empty($item['merchant_user_id'])) return ['scheduled'=>0,'skipped'=>true];
    return mg_campaign_followup_schedule($pdo, ['merchant_user_id'=>(int)$item['merchant_user_id'],'campaign_id'=>(int)$item['campaign_id'],'contact_id'=>!empty($item['contact_id'])?(int)$item['contact_id']:null,'wallet_item_id'=>(int)$item['id'],'trigger_event'=>$eventType,'context'=>$context+['wallet_item_id'=>(string)$item['public_id']]]);
}
function mg_wallet_lifecycle_crm_exists(PDO $pdo, array $item, string $crmEvent): bool
{
    try {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM merchant_crm_contact_events WHERE merchant_user_id=? AND source_type=? AND source_public_id=? AND event_type=?');
        $stmt->execute([(int)$item['merchant_user_id'],'wallet_lifecycle',(string)$item['public_id'],$crmEvent]);
        return (int)$stmt->fetchColumn() > 0;
    } catch (Throwable) { return false; }
}
function mg_wallet_lifecycle_crm(PDO $pdo, array $item, string $eventType, int $actorUserId=0, array $actor=[], array $context=[]): array
{
    $contact = mg_wallet_lifecycle_contact($pdo, $item);
    $campaignType = (string)($contact['campaign_type'] ?? $item['campaign_type'] ?? $item['source_type'] ?? 'wallet_reward');
    $crmEvent = $eventType === 'wallet_item.redeemed' ? 'reward.redeemed' : ($eventType === 'wallet_item.claimed' ? 'reward.claimed' : $eventType);
    if (mg_wallet_lifecycle_crm_exists($pdo, $item, $crmEvent)) return ['schema_ready'=>true,'duplicate'=>true,'event_type'=>$crmEvent,'source_type'=>'wallet_lifecycle','source_public_id'=>(string)$item['public_id']];
    return mg_merchant_crm_record_event($pdo, ['merchant_user_id'=>(int)$item['merchant_user_id'],'campaign_id'=>!empty($item['campaign_id'])?(int)$item['campaign_id']:null,'campaign_type'=>$campaignType,'event_type'=>$crmEvent,'source_type'=>'wallet_lifecycle','source_public_id'=>(string)$item['public_id'],'user_id'=>$actorUserId>0?$actorUserId:(!empty($item['user_id'])?(int)$item['user_id']:null),'email'=>(string)($actor['email'] ?? $contact['email'] ?? ''),'phone'=>(string)($contact['phone'] ?? ''),'name'=>(string)($actor['display_name'] ?? $actor['full_name'] ?? $contact['name'] ?? ''),'value_cents'=>(int)($item['value_cents_snapshot'] ?? 0),'metadata'=>$context+['wallet_item_id'=>(string)$item['public_id'],'campaign_type'=>$campaignType,'wallet_status_event'=>$eventType]]);
}
function mg_wallet_lifecycle_notify(PDO $pdo, array $item, string $eventType, int $actorUserId=0, array $actor=[], array $context=[]): array
{
    $title = (string)($item['title_snapshot'] ?? 'reward');
    $campaignId = !empty($item['campaign_id']) ? (int)$item['campaign_id'] : null;
    $contactId = !empty($item['contact_id']) ? (int)$item['contact_id'] : null;
    $result = [];
    if ($eventType === 'wallet_item.claimed' && !empty($item['merchant_user_id'])) {
        $name = trim((string)($actor['display_name'] ?? $actor['full_name'] ?? $actor['email'] ?? 'A customer'));
        $result['merchant_notification_id'] = mg_create_notification($pdo, (int)$item['merchant_user_id'], 'merchant_reward_claimed', 'Reward claimed', $name.' claimed '.$title, '/merchant-crm.php', ['actor_user_id'=>$actorUserId ?: null,'event_key'=>'wallet.claimed.'.(string)$item['public_id'],'wallet_item_id'=>(int)$item['id'],'campaign_id'=>$campaignId,'contact_id'=>$contactId]);
    }
    if ($eventType === 'wallet_item.redeemed') {
        if (!empty($item['merchant_user_id'])) $result['merchant_notification_id'] = mg_create_notification($pdo, (int)$item['merchant_user_id'], 'merchant_reward_redeemed', 'Reward redeemed', $title.' was redeemed.', '/merchant-crm.php', ['actor_user_id'=>$actorUserId ?: null,'event_key'=>'wallet.redeemed.merchant.'.(string)$item['public_id'],'wallet_item_id'=>(int)$item['id'],'campaign_id'=>$campaignId,'contact_id'=>$contactId]);
        $recipientId = $actorUserId ?: (!empty($item['user_id']) ? (int)$item['user_id'] : 0);
        if ($recipientId > 0) $result['customer_notification_id'] = mg_create_notification($pdo, $recipientId, 'reward_redeemed', 'Reward redeemed', 'Your '.$title.' reward was redeemed.', '/inbox.php', ['actor_user_id'=>(int)($item['merchant_user_id'] ?? 0),'event_key'=>'wallet.redeemed.customer.'.(string)$item['public_id'],'wallet_item_id'=>(int)$item['id'],'campaign_id'=>$campaignId,'contact_id'=>$contactId]);
    }
    return $result;
}
function mg_wallet_lifecycle_automation(PDO $pdo, array $item, string $eventType, int $actorUserId=0, array $actor=[], array $context=[]): array
{
    $crm = mg_wallet_lifecycle_crm($pdo, $item, $eventType, $actorUserId, $actor, $context);
    $followups = mg_wallet_lifecycle_followup($pdo, $item, $eventType, $context+['merchant_crm'=>$crm]);
    $notifications = mg_wallet_lifecycle_notify($pdo, $item, $eventType, $actorUserId, $actor, $context+['merchant_crm'=>$crm,'followups'=>$followups]);
    return ['merchant_crm'=>$crm,'followups'=>$followups,'notifications'=>$notifications];
}
