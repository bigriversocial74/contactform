<?php
declare(strict_types=1);

require_once __DIR__ . '/_atomic_merchant_redemption.php';

final class MgMicrogiftRedemptionReconciliationException extends RuntimeException
{
    public function __construct(string $message,public readonly int $httpStatus=409)
    {
        parent::__construct($message);
    }
}

function mg_microgift_reconcile_completed_redemption(PDO $pdo,string $redemptionPublicId,?int $actorUserId=null): array
{
    if(!$pdo->inTransaction())throw new LogicException('Redemption reconciliation requires an active transaction.');
    $redemptionPublicId=strtolower(trim($redemptionPublicId));
    if(strlen($redemptionPublicId)!==36||preg_match('/^[a-f0-9-]{36}$/',$redemptionPublicId)!==1){
        throw new MgMicrogiftRedemptionReconciliationException('A valid redemption is required.',422);
    }

    $stmt=$pdo->prepare(
        "SELECT r.*,mi.public_id instance_public_id,ml.public_id location_public_id,ml.name location_name,ml.status location_status
         FROM microgift_redemptions r
         INNER JOIN microgift_instances mi ON mi.id=r.instance_id
         LEFT JOIN merchant_locations ml ON ml.id=r.location_id
         WHERE r.public_id=? LIMIT 1 FOR UPDATE"
    );
    $stmt->execute([$redemptionPublicId]);
    $redemption=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$redemption)throw new MgMicrogiftRedemptionReconciliationException('Redemption not found.',404);
    if((string)$redemption['status']!=='completed')throw new MgMicrogiftRedemptionReconciliationException('Redemption is not complete.',409);

    $instance=mg_microgift_load_instance($pdo,(string)$redemption['instance_public_id']);
    $claimantUserId=(int)$redemption['claimant_user_id'];
    $merchantUserId=(int)$redemption['merchant_user_id'];
    $locationId=(int)($redemption['location_id']??0);
    $locationPublicId=trim((string)($redemption['location_public_id']??$redemption['location_reference']??''));
    if($claimantUserId<1||(int)($instance['owner_user_id']??0)!==$claimantUserId){
        throw new MgMicrogiftRedemptionReconciliationException('Redemption claimant does not match current Microgift ownership.',409);
    }
    if($merchantUserId<1||mg_microgift_canonical_merchant($pdo,$instance)!==$merchantUserId){
        throw new MgMicrogiftRedemptionReconciliationException('Redemption merchant authority is invalid.',409);
    }
    if($locationId<1||$locationPublicId===''||(string)($redemption['location_status']??'')!=='active'){
        throw new MgMicrogiftRedemptionReconciliationException('Redemption location is unavailable.',409);
    }
    if(!hash_equals((string)($redemption['location_reference']??''),$locationPublicId)){
        throw new MgMicrogiftRedemptionReconciliationException('Redemption location reference is inconsistent.',409);
    }
    if(!mg_microgift_location_allowed($instance,$locationPublicId)){
        throw new MgMicrogiftRedemptionReconciliationException('Microgift is not eligible at the recorded redemption location.',409);
    }

    if((string)$instance['status']!=='redeemed'){
        if(!in_array((string)$instance['status'],['issued','delivered','claim_pending','claimed','redeemable'],true)){
            throw new MgMicrogiftRedemptionReconciliationException('Microgift cannot be synchronized to redeemed from its current state.',409);
        }
        $pdo->prepare("UPDATE microgift_instances SET status='redeemed',claimed_at=COALESCE(claimed_at,?),redeemed_at=COALESCE(redeemed_at,?),updated_at=NOW() WHERE id=?")
            ->execute([$redemption['redeemed_at'],$redemption['redeemed_at'],(int)$instance['id']]);
        $instance=mg_microgift_load_instance($pdo,(string)$redemption['instance_public_id']);
    }

    $pppmRedemption=null;
    if(!empty($instance['pppm_item_id'])){
        $pppmRedemption=mg_pppm_redeem(
            $pdo,
            (int)$instance['pppm_item_id'],
            $claimantUserId,
            'microgift_redemption',
            $redemptionPublicId,
            ['microgift_instance_id'=>(string)$instance['public_id'],'location_id'=>$locationPublicId,'reconciled'=>true]
        );
    }

    $pdo->prepare('UPDATE microgift_redemptions SET can_tip=1,location_reference=?,updated_at=NOW() WHERE id=?')
        ->execute([$locationPublicId,(int)$redemption['id']]);
    $projection=mg_action_center_project_lifecycle($pdo,$instance,[
        'sender_user_id'=>(int)($instance['issuer_user_id']??0),
        'recipient_user_id'=>$claimantUserId,
        'redemption_id'=>(int)$redemption['id'],
        'merchant_user_id'=>$merchantUserId,
        'location_id'=>$locationId,
        'can_tip'=>1,
        'occurred_at'=>(string)($redemption['redeemed_at']??date('Y-m-d H:i:s')),
    ]);
    $recipientItemId=(string)($projection['recipient_item_id']??'');
    if($recipientItemId===''){
        throw new MgMicrogiftRedemptionReconciliationException('Buyer Action Center redemption projection is unavailable.',409);
    }

    $authority=[
        'merchant_user_id'=>$merchantUserId,
        'location_id'=>$locationId,
        'location_public_id'=>$locationPublicId,
        'location_name'=>(string)($redemption['location_name']??'merchant location'),
    ];
    $notifications=mg_microgift_redemption_confirmations(
        $pdo,
        $instance,
        $claimantUserId,
        $merchantUserId,
        $actorUserId?:$merchantUserId,
        $redemptionPublicId,
        $recipientItemId,
        $authority
    );

    return [
        'redemption_id'=>$redemptionPublicId,
        'instance_id'=>(string)$instance['public_id'],
        'status'=>'completed',
        'lifecycle_status'=>(string)$instance['status'],
        'claimant_user_id'=>$claimantUserId,
        'merchant_user_id'=>$merchantUserId,
        'location_id'=>$locationPublicId,
        'location_name'=>(string)($redemption['location_name']??''),
        'action_center'=>$projection,
        'can_tip'=>true,
        'pppm_redemption'=>$pppmRedemption,
        'customer_notification_id'=>$notifications['customer_notification_id']??null,
        'merchant_notification_id'=>$notifications['merchant_notification_id']??null,
        'reconciled'=>true,
    ];
}
