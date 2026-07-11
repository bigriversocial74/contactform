<?php
declare(strict_types=1);

require_once __DIR__ . '/_loyalty_quest_notifications.php';
require_once __DIR__ . '/_email_worker.php';

function mg_lqn_worker_campaign(array $row): array
{
    return [
        'id'=>(int)($row['campaign_id'] ?? 0),
        'public_id'=>(string)($row['campaign_public_id'] ?? ''),
        'public_slug'=>(string)($row['public_slug'] ?? ''),
        'merchant_user_id'=>(int)($row['merchant_user_id'] ?? 0),
        'merchant_name'=>(string)($row['merchant_name'] ?? 'Microgifter Merchant'),
        'title'=>(string)($row['campaign_title'] ?? 'Loyalty Quest'),
    ];
}

function mg_lqn_worker_expiring_quests(PDO $pdo,int $limit): array
{
    $limit=max(1,min(200,$limit));
    $sql="SELECT lqp.public_id participation_public_id,lqp.participant_user_id,lqp.progress_count,lqp.required_count,c.id campaign_id,c.public_id campaign_public_id,c.public_slug,c.merchant_user_id,c.title campaign_title,c.ends_at,COALESCE(pp.display_name,mw.display_name,u.display_name,u.full_name,'Microgifter Merchant') merchant_name FROM loyalty_quest_participations lqp INNER JOIN campaigns c ON c.id=lqp.campaign_id AND c.merchant_user_id=lqp.merchant_user_id INNER JOIN users u ON u.id=c.merchant_user_id LEFT JOIN public_profiles pp ON pp.user_id=c.merchant_user_id LEFT JOIN merchant_workspaces mw ON mw.merchant_user_id=c.merchant_user_id WHERE c.campaign_type='loyalty_quest' AND c.status='active' AND c.ends_at>NOW() AND c.ends_at<=DATE_ADD(NOW(),INTERVAL 48 HOUR) AND lqp.status IN ('in_progress','pending_review','rejected') AND NOT EXISTS (SELECT 1 FROM message_events me WHERE me.event_key=CONCAT('loyalty-quest:quest_expiring:',lqp.public_id,'-quest-expiring-',DATE_FORMAT(c.ends_at,'%Y%m%d'),':user:',lqp.participant_user_id)) ORDER BY c.ends_at ASC,lqp.id ASC LIMIT {$limit}";
    $rows=$pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];$items=[];
    foreach($rows as $row){
        $source=(string)$row['participation_public_id'].'-quest-expiring-'.gmdate('Ymd',strtotime((string)$row['ends_at']));
        $items[]=mg_lqn_notify_participant($pdo,'quest_expiring',mg_lqn_worker_campaign($row),(int)$row['participant_user_id'],['participation_id'=>(string)$row['participation_public_id'],'source_public_id'=>$source,'expires_at'=>(string)$row['ends_at'],'progress_count'=>(int)$row['progress_count'],'required_count'=>(int)$row['required_count']]);
    }
    return ['processed'=>count($rows),'items'=>$items];
}

function mg_lqn_worker_expiring_rewards(PDO $pdo,int $limit): array
{
    $limit=max(1,min(200,$limit));
    $sql="SELECT wi.public_id wallet_public_id,wi.user_id,wi.pppm_item_id,wi.expires_at,wi.title_snapshot reward_title,c.id campaign_id,c.public_id campaign_public_id,c.public_slug,c.merchant_user_id,c.title campaign_title,COALESCE(pp.display_name,mw.display_name,u.display_name,u.full_name,'Microgifter Merchant') merchant_name FROM wallet_items wi INNER JOIN campaigns c ON c.id=wi.campaign_id AND c.merchant_user_id=wi.merchant_user_id INNER JOIN users u ON u.id=c.merchant_user_id LEFT JOIN public_profiles pp ON pp.user_id=c.merchant_user_id LEFT JOIN merchant_workspaces mw ON mw.merchant_user_id=c.merchant_user_id WHERE wi.source_type='loyalty_quest' AND wi.user_id IS NOT NULL AND wi.status IN ('issued','viewed','claimed') AND wi.expires_at>NOW() AND wi.expires_at<=DATE_ADD(NOW(),INTERVAL 72 HOUR) AND c.campaign_type='loyalty_quest' AND NOT EXISTS (SELECT 1 FROM message_events me WHERE me.event_key=CONCAT('loyalty-quest:reward_expiring:',wi.public_id,'-reward-expiring-',DATE_FORMAT(wi.expires_at,'%Y%m%d'),':user:',wi.user_id)) ORDER BY wi.expires_at ASC,wi.id ASC LIMIT {$limit}";
    $rows=$pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];$items=[];
    foreach($rows as $row){
        $source=(string)$row['wallet_public_id'].'-reward-expiring-'.gmdate('Ymd',strtotime((string)$row['expires_at']));
        $items[]=mg_lqn_notify_participant($pdo,'reward_expiring',mg_lqn_worker_campaign($row),(int)$row['user_id'],['wallet_item_id'=>(string)$row['wallet_public_id'],'pppm_item_id'=>(string)($row['pppm_item_id']??''),'source_public_id'=>$source,'reward_title'=>(string)$row['reward_title'],'expires_at'=>(string)$row['expires_at']]);
    }
    return ['processed'=>count($rows),'items'=>$items];
}

function mg_lqn_worker_redemptions(PDO $pdo,int $limit): array
{
    $limit=max(1,min(200,$limit));
    $sql="SELECT ce.public_id event_public_id,ce.created_at,wi.public_id wallet_public_id,wi.user_id,wi.pppm_item_id,wi.title_snapshot reward_title,c.id campaign_id,c.public_id campaign_public_id,c.public_slug,c.merchant_user_id,c.title campaign_title,COALESCE(pp.display_name,mw.display_name,u.display_name,u.full_name,'Microgifter Merchant') merchant_name FROM campaign_events ce INNER JOIN wallet_items wi ON wi.id=ce.wallet_item_id AND wi.merchant_user_id=ce.merchant_user_id INNER JOIN campaigns c ON c.id=ce.campaign_id AND c.merchant_user_id=ce.merchant_user_id INNER JOIN users u ON u.id=c.merchant_user_id LEFT JOIN public_profiles pp ON pp.user_id=c.merchant_user_id LEFT JOIN merchant_workspaces mw ON mw.merchant_user_id=c.merchant_user_id WHERE ce.event_type='wallet_item.redeemed' AND c.campaign_type='loyalty_quest' AND wi.user_id IS NOT NULL AND ce.created_at>=DATE_SUB(NOW(),INTERVAL 30 DAY) AND (NOT EXISTS (SELECT 1 FROM message_events me WHERE me.event_key=CONCAT('loyalty-quest:redemption_receipt:',ce.public_id,':user:',wi.user_id)) OR NOT EXISTS (SELECT 1 FROM message_events me WHERE me.event_key=CONCAT('loyalty-quest:merchant_redemption_receipt:',ce.public_id,':user:',c.merchant_user_id))) ORDER BY ce.created_at ASC,ce.id ASC LIMIT {$limit}";
    $rows=$pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];$items=[];
    foreach($rows as $row){
        $campaign=mg_lqn_worker_campaign($row);$context=['wallet_item_id'=>(string)$row['wallet_public_id'],'pppm_item_id'=>(string)($row['pppm_item_id']??''),'source_public_id'=>(string)$row['event_public_id'],'reward_title'=>(string)$row['reward_title']];
        $items[]=['participant'=>mg_lqn_notify_participant($pdo,'redemption_receipt',$campaign,(int)$row['user_id'],$context),'merchant'=>mg_lqn_notify_merchant($pdo,'merchant_redemption_receipt',$campaign,$context)];
    }
    return ['processed'=>count($rows),'items'=>$items];
}

function mg_lqn_worker_run(PDO $pdo,int $limit=50): array
{
    $limit=max(1,min(200,$limit));
    $lock=(int)$pdo->query("SELECT GET_LOCK('microgifter_loyalty_quest_notifications',0)")->fetchColumn();
    if($lock!==1)return ['locked'=>false,'message'=>'Another Loyalty Quest notification worker is running.'];
    try{
        $quests=mg_lqn_worker_expiring_quests($pdo,$limit);
        $rewards=mg_lqn_worker_expiring_rewards($pdo,$limit);
        $redemptions=mg_lqn_worker_redemptions($pdo,$limit);
        $email=mg_delivery_run_email_worker($pdo,$limit);
        return ['locked'=>true,'quest_expirations'=>$quests,'reward_expirations'=>$rewards,'redemptions'=>$redemptions,'email'=>$email];
    }finally{
        try{$pdo->query("SELECT RELEASE_LOCK('microgifter_loyalty_quest_notifications')");}catch(Throwable){}
    }
}
