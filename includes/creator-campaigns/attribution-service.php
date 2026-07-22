<?php
declare(strict_types=1);

function mg_creator_campaign_attribution_candidate(PDO $pdo,array $event):?array
{
    if((int)($event['source_id']??0)>0){
        $stmt=$pdo->prepare("SELECT s.*,e.id touch_event_id,e.occurred_at touch_at FROM creator_campaign_tracking_sources s INNER JOIN creator_campaign_tracking_events e ON e.source_id=s.id WHERE s.id=? AND e.id=? LIMIT 1");
        $stmt->execute([(int)$event['source_id'],(int)$event['id']]);
        return $stmt->fetch(PDO::FETCH_ASSOC)?:null;
    }
    $sessionHash=trim((string)($event['session_hash']??''));
    if($sessionHash==='')return null;
    $stmt=$pdo->prepare(
        "SELECT s.*,e.id touch_event_id,e.occurred_at touch_at
         FROM creator_campaign_tracking_events e
         INNER JOIN creator_campaign_tracking_sources s ON s.id=e.source_id
         WHERE e.campaign_id=? AND e.session_hash=? AND e.status='accepted'
           AND e.event_type IN ('click','landing_view','engagement')
           AND e.occurred_at<=?
           AND e.occurred_at>=DATE_SUB(?,INTERVAL s.conversion_window_days DAY)
           AND s.status='active'
         ORDER BY e.occurred_at DESC,e.id DESC LIMIT 100"
    );
    $stmt->execute([(int)$event['campaign_id'],$sessionHash,(string)$event['occurred_at'],(string)$event['occurred_at']]);
    $rows=$stmt->fetchAll(PDO::FETCH_ASSOC)?:[];
    if($rows===[])return null;
    $selected=$rows[0];
    if((string)$selected['attribution_model']==='first_touch')return $rows[count($rows)-1];
    return $selected;
}

function mg_creator_campaign_attribution_decide(PDO $pdo,array $event,?int $actorUserId=null,bool $reprocess=false):array
{
    if(!in_array((string)$event['event_type'],mg_creator_campaign_tracking_conversion_event_types(),true)){
        throw new DomainException('Only conversion events can be attributed.');
    }
    $existingStmt=$pdo->prepare("SELECT * FROM creator_campaign_attributions WHERE conversion_event_id=? LIMIT 1 FOR UPDATE");
    $existingStmt->execute([(int)$event['id']]);
    $existing=$existingStmt->fetch(PDO::FETCH_ASSOC)?:null;
    if($existing&&!$reprocess)return $existing;

    $candidate=((string)$event['status']==='accepted')?mg_creator_campaign_attribution_candidate($pdo,$event):null;
    $sourceId=$candidate?(int)$candidate['id']:null;
    $touchEventId=$candidate?(((int)($candidate['touch_event_id']??0))?:null):null;
    $participantId=$candidate?(int)$candidate['participant_id']:null;
    $creatorUserId=$candidate?(int)$candidate['creator_user_id']:null;
    $model=$candidate?((int)($event['source_id']??0)>0?'direct':(string)$candidate['attribution_model']):'last_touch';
    $status=$candidate?'attributed':'unattributed';
    $confidence=$candidate?((int)($event['source_id']??0)>0?100:90):0;
    $reason=$candidate?'Matched an accepted creator touchpoint inside the configured conversion window.':'No eligible creator touchpoint was found.';
    $windowStart=$candidate?gmdate('Y-m-d H:i:s',strtotime((string)$event['occurred_at'])-((int)$candidate['conversion_window_days']*86400)):null;
    $windowEnd=(string)$event['occurred_at'];

    if($existing){
        $fromSource=(int)($existing['source_id']??0)?:null;
        $pdo->prepare(
            "UPDATE creator_campaign_attributions
             SET touch_event_id=?,source_id=?,participant_id=?,creator_user_id=?,attribution_model=?,status=?,confidence_score=?,
                 decision_reason=?,window_started_at=?,window_ended_at=?,lock_version=lock_version+1,
                 decided_by_user_id=?,attributed_at=NOW(),updated_at=NOW()
             WHERE id=?"
        )->execute([$touchEventId,$sourceId,$participantId,$creatorUserId,$model,$status,$confidence,$reason,$windowStart,$windowEnd,$actorUserId,(int)$existing['id']]);
        $row=mg_creator_campaign_attribution_by_public_id($pdo,(string)$existing['public_id']);
        mg_creator_campaign_attribution_audit($pdo,$row,'reprocessed',$actorUserId,$fromSource,$sourceId,$reason);
        return $row;
    }

    $publicId=mg_creator_campaign_public_id('ccat');
    $pdo->prepare(
        "INSERT INTO creator_campaign_attributions
         (public_id,campaign_id,conversion_event_id,touch_event_id,source_id,participant_id,creator_user_id,attribution_model,status,
          confidence_score,decision_reason,window_started_at,window_ended_at,lock_version,decided_by_user_id,attributed_at,updated_at)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,1,?,NOW(),NOW())"
    )->execute([$publicId,(int)$event['campaign_id'],(int)$event['id'],$touchEventId,$sourceId,$participantId,$creatorUserId,$model,$status,$confidence,$reason,$windowStart,$windowEnd,$actorUserId]);
    $row=mg_creator_campaign_attribution_by_public_id($pdo,$publicId);
    mg_creator_campaign_attribution_audit($pdo,$row,$candidate?'auto_attributed':'auto_unattributed',$actorUserId,null,$sourceId,$reason);
    return $row;
}

function mg_creator_campaign_attribution_override_merchant(PDO $pdo,array $user,string $attributionPublicId,array $input):array
{
    mg_creator_campaign_assert_transaction_boundary($pdo);
    $context=mg_creator_campaign_tracking_merchant_context($pdo,$user,'merchant.creator_attribution.manage');
    $reason=trim((string)($input['reason']??''));
    if($reason===''||mb_strlen($reason)>2000)throw new InvalidArgumentException('A concise override reason is required.');
    $pdo->beginTransaction();
    try{
        $attribution=mg_creator_campaign_attribution_by_public_id($pdo,$attributionPublicId,(int)$context['workspace_id'],true);
        mg_creator_campaign_participation_require_expected_lock($attribution,(int)($input['expected_lock_version']??0));
        $sourcePublicId=trim((string)($input['source_id']??''));
        $source=null;
        if($sourcePublicId!==''){
            $source=mg_creator_campaign_tracking_source_by_public_id($pdo,$sourcePublicId,(int)$context['workspace_id'],null,true);
            if((int)$source['campaign_id']!==(int)$attribution['campaign_id'])throw new DomainException('Override source belongs to another campaign.');
        }
        $fromSource=(int)($attribution['source_id']??0)?:null;
        $toSource=$source?(int)$source['id']:null;
        $pdo->prepare(
            "UPDATE creator_campaign_attributions
             SET touch_event_id=NULL,source_id=?,participant_id=?,creator_user_id=?,attribution_model='manual',
                 status='overridden',confidence_score=100,decision_reason=?,lock_version=lock_version+1,
                 decided_by_user_id=?,attributed_at=NOW(),updated_at=NOW()
             WHERE id=?"
        )->execute([$toSource,$source?(int)$source['participant_id']:null,$source?(int)$source['creator_user_id']:null,
            $reason,(int)$context['actor_user_id'],(int)$attribution['id']]);
        $row=mg_creator_campaign_attribution_by_public_id($pdo,$attributionPublicId,(int)$context['workspace_id']);
        mg_creator_campaign_attribution_audit($pdo,$row,'manual_override',(int)$context['actor_user_id'],$fromSource,$toSource,$reason);
        $pdo->commit();return $row;
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}

function mg_creator_campaign_tracking_invalidate_event_merchant(PDO $pdo,array $user,string $eventPublicId,array $input):array
{
    mg_creator_campaign_assert_transaction_boundary($pdo);
    $context=mg_creator_campaign_tracking_merchant_context($pdo,$user,'merchant.creator_tracking.manage');
    $reason=trim((string)($input['reason']??''));
    if($reason===''||mb_strlen($reason)>2000)throw new InvalidArgumentException('An invalidation reason is required.');
    $pdo->beginTransaction();
    try{
        $event=mg_creator_campaign_tracking_event_by_public_id($pdo,$eventPublicId,(int)$context['workspace_id'],true);
        if((string)$event['status']==='invalidated'){ $pdo->commit(); return $event; }
        $pdo->prepare("UPDATE creator_campaign_tracking_events SET status='invalidated',risk_score=100,risk_flags_json=? WHERE id=?")
            ->execute([mg_creator_campaign_json_encode(['merchant_invalidated']),(int)$event['id']]);
        $stmt=$pdo->prepare("SELECT public_id FROM creator_campaign_attributions WHERE conversion_event_id=? OR touch_event_id=? FOR UPDATE");
        $stmt->execute([(int)$event['id'],(int)$event['id']]);
        $attributionIds=array_map('strval',$stmt->fetchAll(PDO::FETCH_COLUMN)?:[]);
        foreach($attributionIds as $attributionPublicId){
            $attribution=mg_creator_campaign_attribution_by_public_id($pdo,$attributionPublicId,(int)$context['workspace_id'],true);
            if((string)$attribution['status']==='invalidated')continue;
            $pdo->prepare("UPDATE creator_campaign_attributions SET status='invalidated',decision_reason=?,lock_version=lock_version+1,decided_by_user_id=?,updated_at=NOW() WHERE id=?")
                ->execute([$reason,(int)$context['actor_user_id'],(int)$attribution['id']]);
            $updated=mg_creator_campaign_attribution_by_public_id($pdo,$attributionPublicId,(int)$context['workspace_id']);
            mg_creator_campaign_attribution_audit($pdo,$updated,'invalidated',(int)$context['actor_user_id'],(int)($attribution['source_id']??0)?:null,null,$reason,['invalidated_event_id'=>$eventPublicId]);
        }
        $pdo->commit();return mg_creator_campaign_tracking_event_by_public_id($pdo,$eventPublicId,(int)$context['workspace_id']);
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}

function mg_creator_campaign_attribution_reprocess_merchant(PDO $pdo,array $user,string $eventPublicId):array
{
    mg_creator_campaign_assert_transaction_boundary($pdo);
    $context=mg_creator_campaign_tracking_merchant_context($pdo,$user,'merchant.creator_attribution.manage');
    $pdo->beginTransaction();
    try{
        $event=mg_creator_campaign_tracking_event_by_public_id($pdo,$eventPublicId,(int)$context['workspace_id'],true);
        if((string)$event['status']==='invalidated')throw new DomainException('Invalidated events cannot be reprocessed.');
        $row=mg_creator_campaign_attribution_decide($pdo,$event,(int)$context['actor_user_id'],true);
        $pdo->commit();return $row;
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
