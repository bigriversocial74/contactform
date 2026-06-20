<?php
declare(strict_types=1);

function mg_action_center_require_transaction(PDO $pdo): void
{
    if(!$pdo->inTransaction())throw new LogicException('Action Center projections must be updated inside the owning lifecycle transaction.');
}

function mg_action_center_state(array $instance): string
{
    $status=(string)($instance['status']??'issued');
    if(in_array($status,['issued','delivered','claim_pending'],true))return 'claimable';
    if(in_array($status,['claimed','redeemable'],true))return 'redeemable';
    if($status==='redeemed')return 'redeemed';
    if($status==='expired')return 'expired';
    if(in_array($status,['cancelled','revoked','replaced'],true))return 'revoked';
    return 'received';
}

function mg_action_center_projection_upsert(PDO $pdo,array $instance,int $userId,string $folder,string $state,array $context=[]): string
{
    mg_action_center_require_transaction($pdo);

    $instanceId=(int)$instance['id'];
    $senderUserId=(int)($context['sender_user_id']??$instance['issuer_user_id']??0);
    $recipientUserId=(int)($context['recipient_user_id']??$instance['recipient_user_id']??$instance['owner_user_id']??0);
    $redemptionId=isset($context['redemption_id'])?(int)$context['redemption_id']:null;
    $merchantUserId=isset($context['merchant_user_id'])?(int)$context['merchant_user_id']:null;
    $locationId=isset($context['location_id'])?(int)$context['location_id']:null;
    $canTip=(int)($context['can_tip']??($state==='redeemed'?1:0));
    $projectionAt=(string)($context['occurred_at']??$instance['issued_at']??date('Y-m-d H:i:s'));
    $firstReceivedAt=(string)($context['received_at']??$projectionAt);
    $sentAt=$folder==='sent'&&array_key_exists('sent_at',$context)
        ? (string)$context['sent_at']
        : null;
    $claimedAt=$instance['claimed_at']??null;
    $redeemedAt=$instance['redeemed_at']??null;

    $stmt=$pdo->prepare('SELECT id,public_id FROM microgift_inbox_items WHERE instance_id=? AND user_id=? LIMIT 1 FOR UPDATE');
    $stmt->execute([$instanceId,$userId]);
    $row=$stmt->fetch(PDO::FETCH_ASSOC);

    if($row){
        $pdo->prepare('UPDATE microgift_inbox_items SET folder=?,state=?,sender_user_id=?,recipient_user_id=?,redemption_id=COALESCE(?,redemption_id),merchant_user_id=COALESCE(?,merchant_user_id),location_id=COALESCE(?,location_id),can_tip=?,first_received_at=COALESCE(first_received_at,?),sent_at=COALESCE(?,sent_at),claimed_at=COALESCE(?,claimed_at),redeemed_at=COALESCE(?,redeemed_at),updated_at=? WHERE id=?')->execute([$folder,$state,$senderUserId?:null,$recipientUserId?:null,$redemptionId,$merchantUserId,$locationId,$canTip,$firstReceivedAt,$sentAt,$claimedAt,$redeemedAt,$projectionAt,(int)$row['id']]);
        return (string)$row['public_id'];
    }

    $publicId=mg_microgift_uuid();
    $pdo->prepare('INSERT INTO microgift_inbox_items (public_id,instance_id,user_id,folder,sender_user_id,recipient_user_id,state,redemption_id,merchant_user_id,location_id,can_tip,first_received_at,sent_at,claimed_at,redeemed_at,metadata_json,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute([$publicId,$instanceId,$userId,$folder,$senderUserId?:null,$recipientUserId?:null,$state,$redemptionId,$merchantUserId,$locationId,$canTip,$firstReceivedAt,$sentAt,$claimedAt,$redeemedAt,mg_microgift_json([]),$projectionAt,$projectionAt]);
    return $publicId;
}

function mg_action_center_recipient_folder(array $instance): string
{
    $status=(string)($instance['status']??'issued');
    $hasBeenClaimed=!empty($instance['claimed_at']);
    return $hasBeenClaimed||in_array($status,['claimed','redeemable','redeemed'],true)?'claimed':'inbox';
}

function mg_action_center_receive(PDO $pdo,int $instanceId,int $userId,?int $senderUserId=null,array $context=[]): string
{
    mg_action_center_require_transaction($pdo);
    $stmt=$pdo->prepare('SELECT * FROM microgift_instances WHERE id=? LIMIT 1 FOR UPDATE');
    $stmt->execute([$instanceId]);
    $instance=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$instance)throw new RuntimeException('Microgift instance not found for Action Center projection.');
    if($senderUserId!==null)$context['sender_user_id']=$senderUserId;
    $context['recipient_user_id']=$userId;
    return mg_action_center_projection_upsert($pdo,$instance,$userId,mg_action_center_recipient_folder($instance),mg_action_center_state($instance),$context);
}

function mg_action_center_sent(PDO $pdo,int $instanceId,int $senderUserId,int $recipientUserId,array $context=[]): array
{
    mg_action_center_require_transaction($pdo);
    $stmt=$pdo->prepare('SELECT * FROM microgift_instances WHERE id=? LIMIT 1 FOR UPDATE');
    $stmt->execute([$instanceId]);
    $instance=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$instance)throw new RuntimeException('Microgift instance not found for Action Center projection.');
    $context['sender_user_id']=$senderUserId;
    $context['recipient_user_id']=$recipientUserId;
    $state=mg_action_center_state($instance);

    if($senderUserId===$recipientUserId){
        $recipientPublicId=mg_action_center_projection_upsert($pdo,$instance,$recipientUserId,mg_action_center_recipient_folder($instance),$state,$context);
        return ['sent_item_id'=>null,'recipient_inbox_item_id'=>$recipientPublicId];
    }

    $sentPublicId=mg_action_center_projection_upsert($pdo,$instance,$senderUserId,'sent',$state,$context+['can_tip'=>0]);
    $recipientPublicId=mg_action_center_projection_upsert($pdo,$instance,$recipientUserId,mg_action_center_recipient_folder($instance),$state,$context);
    return ['sent_item_id'=>$sentPublicId,'recipient_inbox_item_id'=>$recipientPublicId];
}

function mg_action_center_project_lifecycle(PDO $pdo,array $instance,array $context=[]): array
{
    mg_action_center_require_transaction($pdo);
    $state=mg_action_center_state($instance);
    $senderUserId=(int)($context['sender_user_id']??$instance['issuer_user_id']??0);
    $recipientUserId=(int)($context['recipient_user_id']??$instance['recipient_user_id']??$instance['owner_user_id']??0);
    $recipientFolder=mg_action_center_recipient_folder($instance);
    $context['sender_user_id']=$senderUserId;
    $context['recipient_user_id']=$recipientUserId;
    $result=[];

    if($senderUserId>0&&$senderUserId===$recipientUserId){
        $result['recipient_item_id']=mg_action_center_projection_upsert($pdo,$instance,$recipientUserId,$recipientFolder,$state,$context);
        return $result;
    }
    if($senderUserId>0)$result['sent_item_id']=mg_action_center_projection_upsert($pdo,$instance,$senderUserId,'sent',$state,$context+['can_tip'=>0]);
    if($recipientUserId>0)$result['recipient_item_id']=mg_action_center_projection_upsert($pdo,$instance,$recipientUserId,$recipientFolder,$state,$context);
    return $result;
}
