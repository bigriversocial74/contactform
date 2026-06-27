<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/communications/_communications.php';

function mg_tip_existing_alert(PDO $pdo,int $userId,string $type,string $tipPublicId): ?string
{
    $stmt=$pdo->prepare("SELECT public_id FROM operational_alerts WHERE user_id=? AND alert_type=? AND JSON_UNQUOTE(JSON_EXTRACT(metadata_json,'$.tip_id'))=? ORDER BY id LIMIT 1");
    $stmt->execute([$userId,$type,$tipPublicId]);
    $publicId=$stmt->fetchColumn();
    return $publicId!==false?(string)$publicId:null;
}

function mg_tip_notification_title(array $tip): string
{
    $amount=number_format(((int)$tip['net_cents'])/100,2);
    return 'You received a '.(string)$tip['currency'].' '.$amount.' tip';
}

function mg_tip_notification_context(array $tip): array
{
    $recipientUserId=(int)$tip['recipient_user_id'];
    $merchantUserId=((string)($tip['recipient_wallet_owner_type']??'')==='merchant')?$recipientUserId:null;
    return [
        'tip_id'=>(string)$tip['public_id'],
        'sender_user_id'=>(int)$tip['sender_user_id'],
        'target_type'=>(string)$tip['target_type'],
        'target_reference'=>(string)$tip['target_reference'],
        'amount_cents'=>(int)$tip['amount_cents'],
        'fee_cents'=>(int)$tip['fee_cents'],
        'net_cents'=>(int)$tip['net_cents'],
        'currency'=>(string)$tip['currency'],
        'merchant_user_id'=>$merchantUserId,
    ];
}

function mg_tip_notify_recipient(PDO $pdo,array $tip): string
{
    $tipPublicId=(string)$tip['public_id'];
    $recipientUserId=(int)$tip['recipient_user_id'];
    $existing=mg_tip_existing_alert($pdo,$recipientUserId,'tip_received',$tipPublicId);
    $title=mg_tip_notification_title($tip);
    $amount=number_format(((int)$tip['net_cents'])/100,2);
    $currency=(string)$tip['currency'];
    $context=mg_tip_notification_context($tip);
    $actionUrl=((string)($tip['recipient_wallet_owner_type']??'')==='merchant')?'/merchant-notifications.php?filter=tips':'/inbox.php';
    $notificationId=mg_create_notification($pdo,$recipientUserId,'tip_received',$title,"A {$currency} {$amount} tip was added to your available wallet balance.",$actionUrl,$context+[
        'actor_user_id'=>(int)$tip['sender_user_id'],
        'event_key'=>'tip_received:'.$tipPublicId,
    ]);
    $alertId=$existing??mg_create_operational_alert($pdo,$recipientUserId,'tip_received','info','You received a tip',"A {$currency} {$amount} tip was added to your available wallet balance.",$actionUrl,$context);
    if($existing===null){
        mg_event('tip.received',['tip_id'=>$tipPublicId,'sender_user_id'=>(int)$tip['sender_user_id'],'recipient_user_id'=>$recipientUserId,'net_cents'=>(int)$tip['net_cents'],'currency'=>$currency,'alert_id'=>$alertId,'notification_id'=>$notificationId],$recipientUserId);
    }
    return $notificationId!==''?$notificationId:$alertId;
}

function mg_tip_notify_reversal(PDO $pdo,array $tip,string $reason): string
{
    $tipPublicId=(string)$tip['public_id'];
    $recipientUserId=(int)$tip['recipient_user_id'];
    $existing=mg_tip_existing_alert($pdo,$recipientUserId,'tip_reversed',$tipPublicId);
    if($existing!==null)return $existing;
    $actionUrl=((string)($tip['recipient_wallet_owner_type']??'')==='merchant')?'/merchant-notifications.php?filter=tips':'/inbox.php';
    return mg_create_operational_alert($pdo,$recipientUserId,'tip_reversed','warning','A tip was reversed',mb_substr('A previously posted tip was reversed. '.$reason,0,1000),$actionUrl,['tip_id'=>$tipPublicId,'reason'=>$reason]);
}

function mg_tip_recovery_alert_type(string $kind): string
{
    return match($kind){
        'refund'=>'tip_refunded','dispute_opened'=>'tip_disputed','dispute_won'=>'tip_dispute_won','dispute_lost'=>'tip_dispute_lost','chargeback'=>'tip_chargeback',default=>'tip_recovery',
    };
}

function mg_tip_notify_recovery(PDO $pdo,array $tip,array $result): array
{
    $kind=(string)($result['recovery_type']??'recovery');
    $type=mg_tip_recovery_alert_type($kind);
    $tipPublicId=(string)$tip['public_id'];
    $amount=number_format(((int)($result['amount_cents']??0))/100,2);
    $currency=(string)$tip['currency'];
    [$severity,$title,$message]=match($kind){
        'refund'=>['warning','A tip was refunded',"A {$currency} {$amount} tip refund was processed."],
        'dispute_opened'=>['warning','A tip payment was disputed',"A {$currency} {$amount} tip is under dispute and the related proceeds are on hold."],
        'dispute_won'=>['info','Tip dispute resolved in your favor',"The {$currency} {$amount} dispute hold was released."],
        'dispute_lost'=>['error','Tip dispute was lost',"A {$currency} {$amount} tip was recovered after the provider dispute was lost."],
        'chargeback'=>['error','Tip chargeback processed',"A {$currency} {$amount} chargeback was recovered from the tip balance."],
        default=>['warning','Tip payment recovery update',"A {$currency} {$amount} tip payment recovery update was processed."],
    };
    $metadata=['tip_id'=>$tipPublicId,'recovery_id'=>$result['recovery_id']??null,'recovery_type'=>$kind,'amount_cents'=>(int)($result['amount_cents']??0),'currency'=>$currency];
    $alerts=[];
    $recipient=(int)$tip['recipient_user_id'];
    $existing=mg_tip_existing_alert($pdo,$recipient,$type,$tipPublicId);
    $recipientAction=((string)($tip['recipient_wallet_owner_type']??'')==='merchant')?'/merchant-notifications.php?filter=tips':'/inbox.php';
    $alerts[]=$existing??mg_create_operational_alert($pdo,$recipient,$type,$severity,$title,$message,$recipientAction,$metadata);
    $admins=$pdo->query("SELECT DISTINCT u.id FROM users u INNER JOIN user_roles ur ON ur.user_id=u.id INNER JOIN roles r ON r.id=ur.role_id WHERE u.status='active' AND r.slug IN ('admin','super_admin')")->fetchAll(PDO::FETCH_COLUMN);
    foreach($admins as $adminId){
        $adminId=(int)$adminId;
        if($adminId===$recipient)continue;
        $existing=mg_tip_existing_alert($pdo,$adminId,$type,$tipPublicId);
        $alerts[]=$existing??mg_create_operational_alert($pdo,$adminId,$type,$severity,$title,$message,'/admin/finance.php',$metadata);
    }
    mg_event('tip.recovery_'.$kind,$metadata,(int)$tip['sender_user_id']);
    return array_values(array_unique($alerts));
}
