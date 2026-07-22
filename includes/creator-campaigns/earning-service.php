<?php
declare(strict_types=1);

function mg_creator_campaign_earning_review(PDO $pdo,int $earningEventId,bool $forUpdate=false): ?array
{
    $stmt=$pdo->prepare('SELECT * FROM creator_campaign_earning_reviews WHERE earning_event_id=? LIMIT 1'.($forUpdate?' FOR UPDATE':''));$stmt->execute([$earningEventId]);$row=$stmt->fetch(PDO::FETCH_ASSOC);return $row?:null;
}

function mg_creator_campaign_earning_decide_merchant(PDO $pdo,array $user,string $earningPublicId,string $decision,array $input): array
{
    $context=mg_creator_campaign_compensation_merchant_context($pdo,$user,'merchant.creator_earnings.manage');$decision=strtolower(trim($decision));
    if(!in_array($decision,['approved','held','rejected','reversed'],true))throw new InvalidArgumentException('Earning decision is invalid.');
    $reason=mb_substr(trim((string)($input['reason']??'')),0,2000);if($reason==='')throw new InvalidArgumentException('A decision reason is required.');
    $earning=mg_creator_campaign_earning_event($pdo,$earningPublicId,(int)$context['workspace_id'],false);
    if((string)$earning['event_type']==='reversal')throw new DomainException('Reversal events cannot receive an earning decision.');
    $reversalPublicId=null;
    if($decision==='reversed'){
        $existing=$pdo->prepare("SELECT public_id FROM creator_campaign_earning_events WHERE reversal_of_event_id=? AND event_type='reversal' LIMIT 1");$existing->execute([(int)$earning['id']]);$reversalPublicId=(string)($existing->fetchColumn()?:'');
        if($reversalPublicId===''){$result=mg_creator_campaign_compensation_reverse($pdo,$user,$earningPublicId,['reason'=>$reason]);$reversalPublicId=(string)$result['earning_event_id'];}
    }
    mg_creator_campaign_assert_transaction_boundary($pdo);$pdo->beginTransaction();
    try{
        $earning=mg_creator_campaign_earning_event($pdo,$earningPublicId,(int)$context['workspace_id'],true);$review=mg_creator_campaign_earning_review($pdo,(int)$earning['id'],true);$from=$review?(string)$review['status']:'unreviewed';
        $allowed=['unreviewed'=>['approved','held','rejected','reversed'],'held'=>['approved','rejected','reversed'],'approved'=>['held','rejected','reversed'],'rejected'=>[],'reversed'=>[]];
        if($from===$decision){$pdo->commit();return ['earning_id'=>$earningPublicId,'status'=>$decision,'review_id'=>(string)$review['public_id'],'idempotent'=>true];}
        if(!in_array($decision,$allowed[$from]??[],true))throw new DomainException("Invalid earning decision from {$from} to {$decision}.");
        $reversalId=null;if($reversalPublicId!==null&&$reversalPublicId!==''){$stmt=$pdo->prepare('SELECT id FROM creator_campaign_earning_events WHERE public_id=? LIMIT 1');$stmt->execute([$reversalPublicId]);$reversalId=(int)($stmt->fetchColumn()?:0)?:null;}
        $timestamp=match($decision){'approved'=>'approved_at','held'=>'held_at','rejected'=>'rejected_at','reversed'=>'reversed_at'};
        if($review){$sets=['status=?','decision_reason=?','decided_by_user_id=?','lock_version=lock_version+1',$timestamp.'=NOW()'];$params=[$decision,$reason,(int)$context['actor_user_id']];if($reversalId!==null){$sets[]='reversal_event_id=?';$params[]=$reversalId;}$params[]=(int)$review['id'];$pdo->prepare('UPDATE creator_campaign_earning_reviews SET '.implode(',',$sets).' WHERE id=?')->execute($params);$reviewPublicId=(string)$review['public_id'];}
        else{$reviewPublicId=mg_creator_campaign_public_id('ccer');$fields=['public_id','earning_event_id','status','decision_reason','reversal_event_id','decided_by_user_id',$timestamp,'created_at','updated_at'];$pdo->prepare('INSERT INTO creator_campaign_earning_reviews('.implode(',',$fields).') VALUES (?,?,?,?,?,?,NOW(),NOW(),NOW())')->execute([$reviewPublicId,(int)$earning['id'],$decision,$reason,$reversalId,(int)$context['actor_user_id']]);}
        $pdo->commit();$metadata=['earning_id'=>$earningPublicId,'review_id'=>$reviewPublicId,'from_status'=>$from,'to_status'=>$decision,'reversal_event_id'=>$reversalPublicId];mg_audit('creator_campaign_earning_'.$decision,'creator_campaign_earning',$metadata,(int)$context['actor_user_id']);mg_event('creator_campaign.earning.'.$decision,$metadata,(int)$context['actor_user_id']);return ['earning_id'=>$earningPublicId,'status'=>$decision,'review_id'=>$reviewPublicId,'reversal_event_id'=>$reversalPublicId,'idempotent'=>false];
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
