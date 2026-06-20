<?php
declare(strict_types=1);

require_once __DIR__ . '/_pppm.php';
require_once dirname(__DIR__) . '/entitlements/_lifecycle.php';

function mg_pppm_transfer_owner_canonical(PDO $pdo, string $pppmPublicId, int $newOwnerUserId, string $sourceType, string $sourceReference, ?int $actorUserId = null, array $metadata = []): array
{
    $sourceType=trim($sourceType);
    $sourceReference=trim($sourceReference);
    if($newOwnerUserId<1||$sourceType===''||$sourceReference===''){
        throw new InvalidArgumentException('Valid PPPM owner transfer context is required.');
    }

    $item=mg_pppm_locked_by_public_id($pdo,$pppmPublicId);
    $oldOwner=(int)($item['owner_user_id']??0);
    $fromStatus=(string)($item['status']??'');
    $entitlementKey='owner-sync:'.$pppmPublicId.':'.$newOwnerUserId.':'.$sourceReference;

    $existing=$pdo->prepare('SELECT * FROM entitlement_transfers WHERE idempotency_key=? LIMIT 1 FOR UPDATE');
    $existing->execute([$entitlementKey]);
    if($existingRow=$existing->fetch(PDO::FETCH_ASSOC)){
        if((int)($item['owner_user_id']??0)!==$newOwnerUserId||(int)($item['recipient_user_id']??0)!==$newOwnerUserId){
            throw new RuntimeException('Existing PPPM transfer no longer matches the recorded owner.');
        }
        return [
            'transfer_id'=>(string)$existingRow['public_id'],
            'old_owner_user_id'=>$oldOwner?:null,
            'new_owner_user_id'=>$newOwnerUserId,
            'status'=>$fromStatus,
            'duplicate'=>true,
        ];
    }

    $terminalStatuses=['redeemed','expired','cancelled','refunded','voided'];
    if(in_array($fromStatus,$terminalStatuses,true)){
        throw new RuntimeException('PPPM item cannot be transferred from its current state.');
    }

    $serviceAuthorized=$sourceType==='microgift_claim'
        &&trim((string)($metadata['microgift_instance_id']??''))!=='';
    if($oldOwner<1){
        if(!$serviceAuthorized)throw new RuntimeException('PPPM item does not have a transferable owner.');
    }elseif($actorUserId!==$oldOwner&&!$serviceAuthorized){
        throw new RuntimeException('Only the current PPPM owner can transfer this item.');
    }

    $entitlements=mg_entitlements_sync_pppm_owner(
        $pdo,$pppmPublicId,$newOwnerUserId,$sourceType,$sourceReference,$actorUserId
    );

    if($oldOwner!==$newOwnerUserId||(int)($item['recipient_user_id']??0)!==$newOwnerUserId){
        $toStatus='delivered';
        $pdo->prepare("UPDATE pppm_items SET owner_user_id=?,recipient_user_id=?,status=?,sent_at=COALESCE(sent_at,NOW()),delivered_at=COALESCE(delivered_at,NOW()),version_no=version_no+1,updated_at=NOW() WHERE id=?")
            ->execute([$newOwnerUserId,$newOwnerUserId,$toStatus,(int)$item['id']]);
        $updated=mg_pppm_refresh($pdo,(int)$item['id']);
        mg_pppm_record_event($pdo,$updated,'owner_transferred',$fromStatus,$toStatus,$actorUserId,null,array_merge($metadata,[
            'from_user_id'=>$oldOwner?:null,
            'to_user_id'=>$newOwnerUserId,
            'source_type'=>$sourceType,
            'source_reference'=>$sourceReference,
            'entitlement_transfer_id'=>$entitlements['transfer_id']??null,
        ]));
        mg_event('pppm.owner_transferred',[
            'pppm_item_id'=>$pppmPublicId,
            'from_user_id'=>$oldOwner?:null,
            'to_user_id'=>$newOwnerUserId,
            'from_status'=>$fromStatus,
            'to_status'=>$toStatus,
            'source_type'=>$sourceType,
            'source_reference'=>$sourceReference,
        ],$actorUserId);
        $fromStatus=$toStatus;
    }

    return [
        'transfer_id'=>$entitlements['transfer_id']??null,
        'old_owner_user_id'=>$oldOwner?:null,
        'new_owner_user_id'=>$newOwnerUserId,
        'status'=>$fromStatus,
        'entitlements'=>$entitlements,
        'duplicate'=>(bool)($entitlements['duplicate']??false),
    ];
}
