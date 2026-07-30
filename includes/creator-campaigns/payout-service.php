<?php
declare(strict_types=1);

function mg_creator_campaign_payout_save_profile(PDO $pdo,array $user,string $participantPublicId,array $input): array
{
    $context=mg_creator_campaign_payout_merchant_context($pdo,$user,'merchant.creator_payouts.manage');
    $participant=mg_creator_campaign_payout_participant($pdo,$participantPublicId,(int)$context['workspace_id'],null,false);
    $currency=mg_creator_campaign_compensation_currency($input['currency']??'USD');$status=(string)($input['status']??'pending_review');
    if(!in_array($status,['incomplete','pending_review','eligible','blocked'],true))throw new InvalidArgumentException('Payout profile status is invalid.');
    $minimum=mg_creator_campaign_compensation_minor($input['minimum_payout_minor']??0,'minimum_payout_minor',false);$method=mb_substr(trim((string)($input['method_label']??'')),0,120);$note=mb_substr(trim((string)($input['eligibility_note']??'')),0,2000);
    if(function_exists('mg_creator_campaign_operations_installed')&&mg_creator_campaign_operations_installed($pdo)){
        $policy=mg_creator_campaign_operations_effective_policy($pdo,(int)$context['workspace_id'],$currency);
        if($method===''&&!empty($policy['method_label']))$method=mb_substr((string)$policy['method_label'],0,120);
    }
    mg_creator_campaign_assert_transaction_boundary($pdo);$pdo->beginTransaction();
    try{
        $profile=mg_creator_campaign_payout_profile($pdo,(int)$participant['creator_user_id'],$currency,true);
        if($profile){$pdo->prepare('UPDATE creator_campaign_payout_profiles SET status=?,method_label=?,minimum_payout_minor=?,eligibility_note=?,updated_by_user_id=?,lock_version=lock_version+1 WHERE id=?')->execute([$status,$method?:null,(int)$minimum,$note?:null,(int)$context['actor_user_id'],(int)$profile['id']]);$publicId=(string)$profile['public_id'];}
        else{$publicId=mg_creator_campaign_public_id('ccpp');$pdo->prepare('INSERT INTO creator_campaign_payout_profiles(public_id,creator_user_id,currency,status,method_label,minimum_payout_minor,eligibility_note,created_by_user_id,updated_by_user_id) VALUES (?,?,?,?,?,?,?,?,?)')->execute([$publicId,(int)$participant['creator_user_id'],$currency,$status,$method?:null,(int)$minimum,$note?:null,(int)$context['actor_user_id'],(int)$context['actor_user_id']]);}
        $pdo->commit();return['payout_profile_id'=>$publicId,'status'=>$status];
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
}

function mg_creator_campaign_payout_create(PDO $pdo,array $user,string $participantPublicId,array $input): array
{
    $context=mg_creator_campaign_payout_merchant_context($pdo,$user,'merchant.creator_payouts.manage');$currency=mg_creator_campaign_compensation_currency($input['currency']??'USD');$key=trim((string)($input['idempotency_key']??''));if($key==='')throw new InvalidArgumentException('idempotency_key is required.');
    $holdDays=0;$policyMinimum=0;
    if(function_exists('mg_creator_campaign_operations_installed')&&mg_creator_campaign_operations_installed($pdo)){
        $policy=mg_creator_campaign_operations_effective_policy($pdo,(int)$context['workspace_id'],$currency);
        if(($policy['status']??'active')!=='active')throw new DomainException('The merchant payout policy is paused.');
        $holdDays=max(0,(int)($policy['hold_days']??0));
        $policyMinimum=max(0,(int)($policy['minimum_payout_minor']??0));
    }
    mg_creator_campaign_assert_transaction_boundary($pdo);$pdo->beginTransaction();
    try{
        $participant=mg_creator_campaign_payout_participant($pdo,$participantPublicId,(int)$context['workspace_id'],null,true);$profile=mg_creator_campaign_payout_profile($pdo,(int)$participant['creator_user_id'],$currency,true);
        if(!$profile||$profile['status']!=='eligible')throw new DomainException('An eligible payout profile is required.');
        $idempotencyHash=mg_creator_campaign_idempotency_hash($key);$stmt=$pdo->prepare('SELECT public_id,status,amount_minor,currency FROM creator_campaign_payouts WHERE campaign_id=? AND idempotency_hash=? LIMIT 1 FOR UPDATE');$stmt->execute([(int)$participant['campaign_id'],$idempotencyHash]);$existing=$stmt->fetch(PDO::FETCH_ASSOC);
        if($existing){$pdo->commit();return['payout_id'=>$existing['public_id'],'status'=>$existing['status'],'amount_minor'=>(int)$existing['amount_minor'],'currency'=>$existing['currency'],'idempotent'=>true];}
        $sql="SELECT r.*,e.public_id earning_public_id FROM creator_campaign_budget_reservations r INNER JOIN creator_campaign_earning_events e ON e.id=r.earning_event_id LEFT JOIN creator_campaign_payout_items i ON i.reservation_id=r.id AND i.status IN('scheduled','paid') LEFT JOIN creator_campaign_disputes d ON d.source_type='reservation' AND d.source_public_id=r.public_id AND d.status IN('open','under_review') WHERE r.campaign_id=? AND r.participant_id=? AND r.creator_user_id=? AND r.currency=? AND r.status='committed' AND i.id IS NULL AND d.id IS NULL";
        $params=[(int)$participant['campaign_id'],(int)$participant['id'],(int)$participant['creator_user_id'],$currency];
        if($holdDays>0){$sql.=' AND r.committed_at<=?';$params[]=gmdate('Y-m-d H:i:s',time()-($holdDays*86400));}
        $sql.=' ORDER BY r.id ASC FOR UPDATE';
        $stmt=$pdo->prepare($sql);$stmt->execute($params);$reservations=$stmt->fetchAll(PDO::FETCH_ASSOC)?:[];
        $total=0;foreach($reservations as$r)$total+=(int)$r['amount_minor'];
        if($total<1)throw new DomainException($holdDays>0?'No eligible committed reservations have completed the payout hold period.':'No eligible committed reservations are available.');
        $effectiveMinimum=max((int)$profile['minimum_payout_minor'],$policyMinimum);
        if($total<$effectiveMinimum)throw new DomainException('Eligible committed balance is below the effective payout minimum.');
        $publicId=mg_creator_campaign_public_id('ccpo');$pdo->prepare('INSERT INTO creator_campaign_payouts(public_id,campaign_id,participant_id,creator_user_id,payout_profile_id,currency,amount_minor,status,idempotency_hash,created_by_user_id,updated_by_user_id) VALUES (?,?,?,?,?,?,?,?,?,?,?)')->execute([$publicId,(int)$participant['campaign_id'],(int)$participant['id'],(int)$participant['creator_user_id'],(int)$profile['id'],$currency,$total,'draft',$idempotencyHash,(int)$context['actor_user_id'],(int)$context['actor_user_id']]);$payoutId=(int)$pdo->lastInsertId();
        foreach($reservations as$r){$pdo->prepare('INSERT INTO creator_campaign_payout_items(public_id,payout_id,reservation_id,earning_event_id,amount_minor,currency,status) VALUES (?,?,?,?,?,?,?)')->execute([mg_creator_campaign_public_id('ccpi'),$payoutId,(int)$r['id'],(int)$r['earning_event_id'],(int)$r['amount_minor'],$currency,'scheduled']);}
        $payout=['id'=>$payoutId,'public_id'=>$publicId,'amount_minor'=>$total,'currency'=>$currency];mg_creator_campaign_payout_append_event($pdo,$payout,'created',null,'draft',(int)$context['actor_user_id'],'payout-created:'.$publicId,'Payout record created from committed campaign reservations after policy checks.');
        $pdo->commit();return['payout_id'=>$publicId,'status'=>'draft','amount_minor'=>$total,'currency'=>$currency,'item_count'=>count($reservations),'hold_days'=>$holdDays,'effective_minimum_payout_minor'=>$effectiveMinimum,'manual_approval_required'=>true];
    }catch(Throwable$e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
}

function mg_creator_campaign_payout_transition(PDO $pdo,array $user,string $payoutPublicId,string $toStatus,array $input): array
{
    $context=mg_creator_campaign_payout_merchant_context($pdo,$user,'merchant.creator_payouts.manage');mg_creator_campaign_assert_transaction_boundary($pdo);$pdo->beginTransaction();
    try{
        $payout=mg_creator_campaign_payout_by_public_id($pdo,$payoutPublicId,(int)$context['workspace_id'],null,true);$from=(string)$payout['status'];if($from===$toStatus){$pdo->commit();return['payout_id'=>$payoutPublicId,'status'=>$toStatus,'idempotent'=>true];}mg_creator_campaign_payout_assert_transition($from,$toStatus);
        if(in_array($toStatus,['approved','processing','paid'],true)&&function_exists('mg_creator_campaign_operations_installed')&&mg_creator_campaign_operations_installed($pdo)){
            $policy=mg_creator_campaign_operations_effective_policy($pdo,(int)$context['workspace_id'],(string)$payout['currency']);
            if(($policy['status']??'active')!=='active')throw new DomainException('The merchant payout policy is paused.');
        }
        if(in_array($toStatus,['approved','processing','paid'],true)&&mg_creator_campaign_payout_has_active_dispute($pdo,$payout))throw new DomainException('This payout has an active dispute and cannot advance.');
        $provider=mb_substr(trim((string)($input['provider_reference']??'')),0,255);if($toStatus==='paid'&&$provider==='')throw new InvalidArgumentException('provider_reference is required before marking a payout paid.');$reason=mb_substr(trim((string)($input['reason']??'')),0,2000);
        $timestampColumn=match($toStatus){'approved'=>'approved_at','processing'=>'processing_at','paid'=>'paid_at','failed'=>'failed_at','cancelled'=>'cancelled_at','reversed'=>'reversed_at',default=>null};
        $sets=['status=?','provider_reference=?','failure_reason=?','updated_by_user_id=?','lock_version=lock_version+1'];$params=[$toStatus,$provider?:($payout['provider_reference']??null),$toStatus==='failed'?($reason?:'Payout processing failed.'):null,(int)$context['actor_user_id']];if($timestampColumn!==null)$sets[]=$timestampColumn.'=NOW()';$params[]=(int)$payout['id'];$pdo->prepare('UPDATE creator_campaign_payouts SET '.implode(',',$sets).' WHERE id=?')->execute($params);
        if($toStatus==='paid')$pdo->prepare("UPDATE creator_campaign_payout_items SET status='paid' WHERE payout_id=? AND status='scheduled'")->execute([(int)$payout['id']]);elseif($toStatus==='cancelled')$pdo->prepare("UPDATE creator_campaign_payout_items SET status='released' WHERE payout_id=? AND status='scheduled'")->execute([(int)$payout['id']]);elseif($toStatus==='reversed')$pdo->prepare("UPDATE creator_campaign_payout_items SET status='reversed' WHERE payout_id=? AND status='paid'")->execute([(int)$payout['id']]);
        $eventType=in_array($toStatus,['approved','processing','paid','failed','cancelled','reversed'],true)?$toStatus:'note';mg_creator_campaign_payout_append_event($pdo,$payout,$eventType,$from,$toStatus,(int)$context['actor_user_id'],'payout-transition:'.$payoutPublicId.':'.$toStatus,$reason?:null,$provider?:null);
        $pdo->commit();return['payout_id'=>$payoutPublicId,'status'=>$toStatus];
    }catch(Throwable$e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
}

function mg_creator_campaign_dispute_open(PDO $pdo,array $user,string $sourceType,string $sourcePublicId,string $reason,bool $creatorRequest): array
{
    $permission=$creatorRequest?'creator.campaign_disputes.manage_own':'merchant.creator_disputes.manage';$context=$creatorRequest?mg_creator_campaign_payout_creator_context($pdo,$user,$permission):mg_creator_campaign_payout_merchant_context($pdo,$user,$permission);$workspaceId=$creatorRequest?null:(int)$context['workspace_id'];$creatorUserId=$creatorRequest?(int)$context['creator_user_id']:null;$reason=mb_substr(trim($reason),0,2000);if($reason==='')throw new InvalidArgumentException('reason is required.');
    $source=mg_creator_campaign_payout_source($pdo,$sourceType,$sourcePublicId,$workspaceId,$creatorUserId);mg_creator_campaign_assert_transaction_boundary($pdo);$pdo->beginTransaction();
    try{
        $stmt=$pdo->prepare("SELECT public_id,status FROM creator_campaign_disputes WHERE active_source_key=? LIMIT 1 FOR UPDATE");$stmt->execute([$sourceType.':'.$sourcePublicId]);$existing=$stmt->fetch(PDO::FETCH_ASSOC);if($existing){$pdo->commit();return['dispute_id'=>$existing['public_id'],'status'=>$existing['status'],'idempotent'=>true];}
        $publicId=mg_creator_campaign_public_id('ccdi');$pdo->prepare('INSERT INTO creator_campaign_disputes(public_id,campaign_id,participant_id,creator_user_id,source_type,source_public_id,status,reason,opened_by_user_id,opened_at) VALUES (?,?,?,?,?,?,?,?,?,NOW())')->execute([$publicId,$source['campaign_id'],$source['participant_id'],$source['creator_user_id'],$sourceType,$sourcePublicId,'open',$reason,(int)$context['actor_user_id']]);$id=(int)$pdo->lastInsertId();$dispute=['id'=>$id,'public_id'=>$publicId,'source_type'=>$sourceType,'source_public_id'=>$sourcePublicId];mg_creator_campaign_dispute_append_event($pdo,$dispute,'opened',null,'open',(int)$context['actor_user_id'],'dispute-opened:'.$publicId,$reason);$pdo->commit();return['dispute_id'=>$publicId,'status'=>'open'];
    }catch(Throwable$e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
}

function mg_creator_campaign_dispute_transition(PDO $pdo,array $user,string $disputePublicId,string $toStatus,array $input): array
{
    $context=mg_creator_campaign_payout_merchant_context($pdo,$user,'merchant.creator_disputes.manage');$allowed=['open'=>['under_review','resolved_upheld','resolved_adjusted','rejected'],'under_review'=>['resolved_upheld','resolved_adjusted','rejected'],'resolved_upheld'=>['closed'],'resolved_adjusted'=>['closed'],'rejected'=>['closed'],'closed'=>[]];mg_creator_campaign_assert_transaction_boundary($pdo);$pdo->beginTransaction();
    try{$dispute=mg_creator_campaign_dispute_by_public_id($pdo,$disputePublicId,(int)$context['workspace_id'],null,true);$from=(string)$dispute['status'];if($from===$toStatus){$pdo->commit();return['dispute_id'=>$disputePublicId,'status'=>$toStatus,'idempotent'=>true];}if(!in_array($toStatus,$allowed[$from]??[],true))throw new DomainException("Invalid dispute transition from {$from} to {$toStatus}.");$note=mb_substr(trim((string)($input['resolution_note']??$input['note']??'')),0,4000);if(in_array($toStatus,['resolved_upheld','resolved_adjusted','rejected'],true)&&$note==='')throw new InvalidArgumentException('resolution_note is required.');$sets=['status=?','resolution_note=?','lock_version=lock_version+1'];$params=[$toStatus,$note?:null];if(in_array($toStatus,['resolved_upheld','resolved_adjusted','rejected'],true)){$sets[]='resolved_by_user_id=?';$sets[]='resolved_at=NOW()';$params[]=(int)$context['actor_user_id'];}if($toStatus==='closed')$sets[]='closed_at=NOW()';$params[]=(int)$dispute['id'];$pdo->prepare('UPDATE creator_campaign_disputes SET '.implode(',',$sets).' WHERE id=?')->execute($params);$eventType=match($toStatus){'under_review'=>'review_started','resolved_upheld','resolved_adjusted'=>'resolved','rejected'=>'rejected','closed'=>'closed',default=>'note'};mg_creator_campaign_dispute_append_event($pdo,$dispute,$eventType,$from,$toStatus,(int)$context['actor_user_id'],'dispute-transition:'.$disputePublicId.':'.$toStatus,$note?:null);$pdo->commit();return['dispute_id'=>$disputePublicId,'status'=>$toStatus];}catch(Throwable$e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
}
