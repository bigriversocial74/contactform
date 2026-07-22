<?php
declare(strict_types=1);

function mg_creator_campaign_payout_profile(PDO $pdo,int $creatorUserId,string $currency,bool $forUpdate=false): ?array
{
    $sql='SELECT * FROM creator_campaign_payout_profiles WHERE creator_user_id=? AND currency=? LIMIT 1'.($forUpdate?' FOR UPDATE':'');
    $stmt=$pdo->prepare($sql);$stmt->execute([$creatorUserId,$currency]);$row=$stmt->fetch(PDO::FETCH_ASSOC);
    return $row?:null;
}

function mg_creator_campaign_payout_participant(PDO $pdo,string $publicId,?int $workspaceId=null,?int $creatorUserId=null,bool $forUpdate=false): array
{
    return mg_creator_campaign_tracking_participant_by_public_id($pdo,$publicId,$workspaceId,$creatorUserId,$forUpdate);
}

function mg_creator_campaign_payout_by_public_id(PDO $pdo,string $publicId,?int $workspaceId=null,?int $creatorUserId=null,bool $forUpdate=false): array
{
    $sql="SELECT p.*,cc.public_id campaign_public_id,cc.title campaign_title,cc.workspace_id,cp.display_name creator_name,pp.status profile_status,pp.method_label,pp.minimum_payout_minor
      FROM creator_campaign_payouts p
      INNER JOIN creator_campaigns cc ON cc.id=p.campaign_id
      INNER JOIN creator_campaign_participants participant ON participant.id=p.participant_id
      INNER JOIN creator_profiles cp ON cp.id=participant.creator_profile_id
      INNER JOIN creator_campaign_payout_profiles pp ON pp.id=p.payout_profile_id
      WHERE p.public_id=?";
    $params=[trim($publicId)];
    if($workspaceId!==null){$sql.=' AND cc.workspace_id=?';$params[]=$workspaceId;}
    if($creatorUserId!==null){$sql.=' AND p.creator_user_id=?';$params[]=$creatorUserId;}
    $sql.=' LIMIT 1'.($forUpdate?' FOR UPDATE':'');
    $stmt=$pdo->prepare($sql);$stmt->execute($params);$row=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$row)throw new RuntimeException('Creator Campaign payout not found.');
    return $row;
}

function mg_creator_campaign_dispute_by_public_id(PDO $pdo,string $publicId,?int $workspaceId=null,?int $creatorUserId=null,bool $forUpdate=false): array
{
    $sql="SELECT d.*,cc.workspace_id,cc.public_id campaign_public_id,cc.title campaign_title,cp.display_name creator_name
      FROM creator_campaign_disputes d
      INNER JOIN creator_campaigns cc ON cc.id=d.campaign_id
      INNER JOIN creator_campaign_participants participant ON participant.id=d.participant_id
      INNER JOIN creator_profiles cp ON cp.id=participant.creator_profile_id
      WHERE d.public_id=?";
    $params=[trim($publicId)];
    if($workspaceId!==null){$sql.=' AND cc.workspace_id=?';$params[]=$workspaceId;}
    if($creatorUserId!==null){$sql.=' AND d.creator_user_id=?';$params[]=$creatorUserId;}
    $sql.=' LIMIT 1'.($forUpdate?' FOR UPDATE':'');
    $stmt=$pdo->prepare($sql);$stmt->execute($params);$row=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$row)throw new RuntimeException('Creator Campaign dispute not found.');
    return $row;
}

function mg_creator_campaign_payout_source(PDO $pdo,string $sourceType,string $sourcePublicId,?int $workspaceId=null,?int $creatorUserId=null): array
{
    if(!in_array($sourceType,['payout','reservation','earning'],true))throw new InvalidArgumentException('source_type is invalid.');
    if($sourceType==='payout'){
        $payout=mg_creator_campaign_payout_by_public_id($pdo,$sourcePublicId,$workspaceId,$creatorUserId,false);
        return ['campaign_id'=>(int)$payout['campaign_id'],'participant_id'=>(int)$payout['participant_id'],'creator_user_id'=>(int)$payout['creator_user_id'],'source_type'=>'payout','source_public_id'=>$sourcePublicId];
    }
    if($sourceType==='reservation'){
        $sql="SELECT r.campaign_id,r.participant_id,r.creator_user_id,cc.workspace_id FROM creator_campaign_budget_reservations r INNER JOIN creator_campaigns cc ON cc.id=r.campaign_id WHERE r.public_id=?";
        $params=[$sourcePublicId];if($workspaceId!==null){$sql.=' AND cc.workspace_id=?';$params[]=$workspaceId;}if($creatorUserId!==null){$sql.=' AND r.creator_user_id=?';$params[]=$creatorUserId;}$sql.=' LIMIT 1';
    }else{
        $sql="SELECT e.campaign_id,e.participant_id,e.creator_user_id,cc.workspace_id FROM creator_campaign_earning_events e INNER JOIN creator_campaigns cc ON cc.id=e.campaign_id WHERE e.public_id=?";
        $params=[$sourcePublicId];if($workspaceId!==null){$sql.=' AND cc.workspace_id=?';$params[]=$workspaceId;}if($creatorUserId!==null){$sql.=' AND e.creator_user_id=?';$params[]=$creatorUserId;}$sql.=' LIMIT 1';
    }
    $stmt=$pdo->prepare($sql);$stmt->execute($params);$row=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$row)throw new RuntimeException('Creator Campaign dispute source not found.');
    return ['campaign_id'=>(int)$row['campaign_id'],'participant_id'=>(int)$row['participant_id'],'creator_user_id'=>(int)$row['creator_user_id'],'source_type'=>$sourceType,'source_public_id'=>$sourcePublicId];
}

function mg_creator_campaign_payout_append_event(PDO $pdo,array $payout,string $eventType,?string $fromStatus,?string $toStatus,int $actorUserId,string $idempotencyKey,?string $reason=null,?string $providerReference=null): array
{
    $publicId=mg_creator_campaign_public_id('ccpe');$hash=mg_creator_campaign_idempotency_hash($idempotencyKey);
    $snapshot=['payout_id'=>$payout['public_id']??null,'amount_minor'=>(int)($payout['amount_minor']??0),'currency'=>$payout['currency']??null,'from_status'=>$fromStatus,'to_status'=>$toStatus,'provider_reference'=>$providerReference];
    $stmt=$pdo->prepare('INSERT INTO creator_campaign_payout_events(public_id,payout_id,event_type,from_status,to_status,provider_reference,reason,idempotency_hash,snapshot_json,actor_user_id) VALUES (?,?,?,?,?,?,?,?,?,?)');
    $stmt->execute([$publicId,(int)$payout['id'],$eventType,$fromStatus,$toStatus,$providerReference,$reason,$hash,mg_creator_campaign_json_encode($snapshot),$actorUserId]);
    return ['event_id'=>$publicId];
}

function mg_creator_campaign_dispute_append_event(PDO $pdo,array $dispute,string $eventType,?string $fromStatus,?string $toStatus,int $actorUserId,string $idempotencyKey,?string $note=null): array
{
    $publicId=mg_creator_campaign_public_id('ccde');$snapshot=['dispute_id'=>$dispute['public_id']??null,'source_type'=>$dispute['source_type']??null,'source_public_id'=>$dispute['source_public_id']??null,'from_status'=>$fromStatus,'to_status'=>$toStatus];
    $stmt=$pdo->prepare('INSERT INTO creator_campaign_dispute_events(public_id,dispute_id,event_type,from_status,to_status,note,idempotency_hash,snapshot_json,actor_user_id) VALUES (?,?,?,?,?,?,?,?,?)');
    $stmt->execute([$publicId,(int)$dispute['id'],$eventType,$fromStatus,$toStatus,$note,mg_creator_campaign_idempotency_hash($idempotencyKey),mg_creator_campaign_json_encode($snapshot),$actorUserId]);
    return ['event_id'=>$publicId];
}

function mg_creator_campaign_payout_has_active_dispute(PDO $pdo,array $payout): bool
{
    $stmt=$pdo->prepare("SELECT COUNT(*) FROM creator_campaign_disputes d WHERE d.status IN('open','under_review') AND ((d.source_type='payout' AND d.source_public_id=?) OR (d.source_type='reservation' AND d.source_public_id IN(SELECT r.public_id FROM creator_campaign_payout_items i INNER JOIN creator_campaign_budget_reservations r ON r.id=i.reservation_id WHERE i.payout_id=?)) OR (d.source_type='earning' AND d.source_public_id IN(SELECT e.public_id FROM creator_campaign_payout_items i INNER JOIN creator_campaign_earning_events e ON e.id=i.earning_event_id WHERE i.payout_id=?)))");
    $stmt->execute([(string)$payout['public_id'],(int)$payout['id'],(int)$payout['id']]);
    return (int)$stmt->fetchColumn()>0;
}
