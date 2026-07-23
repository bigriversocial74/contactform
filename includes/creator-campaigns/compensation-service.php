<?php
declare(strict_types=1);

function mg_creator_campaign_compensation_save_rule(PDO $pdo,array $user,string $campaignPublicId,array $input): array
{
    $context=mg_creator_campaign_compensation_merchant_context($pdo,$user,'merchant.creator_compensation.manage');
    mg_creator_campaign_assert_transaction_boundary($pdo);
    $pdo->beginTransaction();
    try{
        $campaign=mg_creator_campaign_compensation_campaign($pdo,$campaignPublicId,(int)$context['workspace_id'],true);
        $type=(string)($input['compensation_type']??'');
        $trigger=(string)($input['trigger_type']??'');
        if(!in_array($type,mg_creator_campaign_compensation_types(),true)) throw new InvalidArgumentException('compensation_type is invalid.');
        if(!in_array($trigger,mg_creator_campaign_compensation_triggers(),true)) throw new InvalidArgumentException('trigger_type is invalid.');
        $allowedTriggers=[
            'fixed_deliverable'=>['deliverable_verified'],
            'percent_conversion'=>['purchase_attributed','claim_attributed','redemption_attributed'],
            'flat_conversion'=>['purchase_attributed','claim_attributed','redemption_attributed'],
            'milestone'=>['milestone_approved'],
            'manual_only'=>['manual'],
        ];
        if(!in_array($trigger,$allowedTriggers[$type]??[],true)) throw new InvalidArgumentException('trigger_type is not compatible with compensation_type.');
        $title=mb_substr(trim((string)($input['title']??'')),0,180);
        $code=strtolower(trim((string)($input['rule_code']??'')));
        if($title===''||!preg_match('/^[a-z0-9][a-z0-9_-]{2,79}$/',$code)) throw new InvalidArgumentException('title and a valid rule_code are required.');
        $currency=mg_creator_campaign_compensation_currency($input['currency']??'USD');
        $flat=mg_creator_campaign_compensation_minor($input['flat_amount_minor']??null,'flat_amount_minor',true);
        $rate=mg_creator_campaign_compensation_minor($input['rate_bps']??null,'rate_bps',true);
        if($rate!==null&&$rate>10000) throw new InvalidArgumentException('rate_bps cannot exceed 10000.');
        $minimum=mg_creator_campaign_compensation_minor($input['minimum_source_amount_minor']??null,'minimum_source_amount_minor',true);
        $maximum=mg_creator_campaign_compensation_minor($input['maximum_earning_minor']??null,'maximum_earning_minor',true);
        if(in_array($type,['fixed_deliverable','flat_conversion','milestone'],true)&&($flat??0)<1) throw new InvalidArgumentException('A positive flat_amount_minor is required.');
        if($type==='percent_conversion'&&($rate??0)<1) throw new InvalidArgumentException('A positive rate_bps is required.');
        $terms=trim((string)($input['terms_text']??''));
        if($terms==='') throw new InvalidArgumentException('terms_text is required.');
        $snapshot=mg_creator_campaign_compensation_rule_snapshot([
            'compensation_type'=>$type,'trigger_type'=>$trigger,'currency'=>$currency,'flat_amount_minor'=>$flat,
            'rate_bps'=>$rate,'minimum_source_amount_minor'=>$minimum,'maximum_earning_minor'=>$maximum,'terms_text'=>$terms,
        ]);
        $hash=hash('sha256',mg_creator_campaign_json_encode($snapshot));
        $ruleId=0;$publicId=trim((string)($input['rule_id']??''));
        if($publicId!==''){
            $rule=mg_creator_campaign_compensation_rule($pdo,$publicId,(int)$context['workspace_id'],true);
            if((int)$rule['campaign_id']!==(int)$campaign['id']) throw new DomainException('Rule does not belong to this campaign.');
            $ruleId=(int)$rule['id'];
            $pdo->prepare('UPDATE creator_campaign_compensation_rules SET rule_code=?,title=?,description=?,compensation_type=?,trigger_type=?,updated_by_user_id=?,lock_version=lock_version+1 WHERE id=?')
                ->execute([$code,$title,mb_substr(trim((string)($input['description']??'')),0,16000),$type,$trigger,(int)$context['actor_user_id'],$ruleId]);
        }else{
            $publicId=mg_creator_campaign_public_id('cccr');
            $pdo->prepare('INSERT INTO creator_campaign_compensation_rules(public_id,campaign_id,rule_code,title,description,compensation_type,trigger_type,status,created_by_user_id,updated_by_user_id) VALUES (?,?,?,?,?,?,?,?,?,?)')
                ->execute([$publicId,(int)$campaign['id'],$code,$title,mb_substr(trim((string)($input['description']??'')),0,16000),$type,$trigger,'draft',(int)$context['actor_user_id'],(int)$context['actor_user_id']]);
            $ruleId=(int)$pdo->lastInsertId();
        }
        $stmt=$pdo->prepare('SELECT id,public_id,version_number FROM creator_campaign_compensation_rule_versions WHERE rule_id=? AND content_hash=? LIMIT 1');
        $stmt->execute([$ruleId,$hash]);$existing=$stmt->fetch(PDO::FETCH_ASSOC);
        if($existing){
            $pdo->commit();
            return ['rule_id'=>$publicId,'version_id'=>$existing['public_id'],'version_number'=>(int)$existing['version_number'],'idempotent'=>true];
        }
        $stmt=$pdo->prepare('SELECT COALESCE(MAX(version_number),0)+1 FROM creator_campaign_compensation_rule_versions WHERE rule_id=?');
        $stmt->execute([$ruleId]);$version=(int)$stmt->fetchColumn();
        $versionPublicId=mg_creator_campaign_public_id('cccv');
        $pdo->prepare('INSERT INTO creator_campaign_compensation_rule_versions(public_id,rule_id,campaign_id,version_number,version_status,currency,flat_amount_minor,rate_bps,minimum_source_amount_minor,maximum_earning_minor,terms_text,calculation_snapshot_json,content_hash,effective_from,created_by_user_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),?)')
            ->execute([$versionPublicId,$ruleId,(int)$campaign['id'],$version,'active',$currency,$flat,$rate,$minimum,$maximum,$terms,mg_creator_campaign_json_encode($snapshot),$hash,(int)$context['actor_user_id']]);
        $versionId=(int)$pdo->lastInsertId();
        $stmt=$pdo->prepare("SELECT public_id,title FROM creator_campaign_compensation_rules WHERE campaign_id=? AND trigger_type=? AND status='active' AND id<>? LIMIT 1 FOR UPDATE");
        $stmt->execute([(int)$campaign['id'],$trigger,$ruleId]);
        if($stmt->fetch(PDO::FETCH_ASSOC)) throw new DomainException('Another active compensation rule already governs this campaign trigger.');
        $pdo->prepare("UPDATE creator_campaign_compensation_rule_versions SET version_status='superseded',effective_to=NOW() WHERE rule_id=? AND id<>? AND version_status='active'")->execute([$ruleId,$versionId]);
        $pdo->prepare("UPDATE creator_campaign_compensation_rules SET current_version_id=?,status='active',updated_by_user_id=?,lock_version=lock_version+1 WHERE id=?")->execute([$versionId,(int)$context['actor_user_id'],$ruleId]);
        $pdo->commit();
        return ['rule_id'=>$publicId,'version_id'=>$versionPublicId,'version_number'=>$version,'idempotent'=>false];
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}

function mg_creator_campaign_compensation_calculate(array $rule,int $sourceAmountMinor): int
{
    if($sourceAmountMinor<(int)($rule['minimum_source_amount_minor']??0)) return 0;
    $amount=match((string)$rule['compensation_type']){
        'percent_conversion'=>mg_creator_campaign_compensation_percent_minor($sourceAmountMinor,(int)($rule['rate_bps']??0)),
        'fixed_deliverable','flat_conversion','milestone'=>(int)($rule['flat_amount_minor']??0),
        default=>0,
    };
    $maximum=(int)($rule['maximum_earning_minor']??0);
    return $maximum>0?min($amount,$maximum):$amount;
}

function mg_creator_campaign_compensation_record_source_earning(PDO $pdo,array $payload): array
{
    mg_creator_campaign_compensation_assert_schema($pdo);
    mg_creator_campaign_assert_transaction_boundary($pdo);
    $sourceType=(string)($payload['source_type']??'');
    $sourcePublicId=trim((string)($payload['source_public_id']??''));
    $idempotencyKey=trim((string)($payload['idempotency_key']??''));
    if($sourcePublicId===''||$idempotencyKey==='') throw new InvalidArgumentException('source_public_id and idempotency_key are required.');
    $source=mg_creator_campaign_compensation_source($pdo,$sourceType,$sourcePublicId);
    $rule=mg_creator_campaign_compensation_active_rule($pdo,(int)$source['campaign_id'],(string)$source['trigger_type']);
    if(!$rule) throw new DomainException('No active compensation rule matches this source.');
    $amount=mg_creator_campaign_compensation_calculate($rule,(int)($source['source_amount_minor']??0));
    if($amount<1) throw new DomainException('The compensation calculation produced no earning.');
    $idempotencyHash=mg_creator_campaign_idempotency_hash($idempotencyKey);
    $sourceHash=hash('sha256',$sourceType.'|'.$sourcePublicId.'|'.(string)$rule['content_hash']);
    $snapshot=['rule_version_id'=>$rule['version_public_id'],'source_type'=>$sourceType,'source_public_id'=>$sourcePublicId,'source_amount_minor'=>(int)($source['source_amount_minor']??0),'amount_minor'=>$amount,'currency'=>$rule['currency']];
    $actor=(int)($payload['actor_user_id']??$source['creator_user_id']??0);
    $pdo->beginTransaction();
    try{
        $publicId=mg_creator_campaign_public_id('ccee');
        $pdo->prepare('INSERT INTO creator_campaign_earning_events(public_id,campaign_id,participant_id,creator_user_id,agreement_version_id,rule_id,rule_version_id,event_type,source_type,source_public_id,source_amount_minor,amount_minor,currency,idempotency_hash,source_hash,calculation_snapshot_json,reason,created_by_user_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([$publicId,(int)$source['campaign_id'],(int)$source['participant_id'],(int)$source['creator_user_id'],(int)$source['agreement_version_id'],(int)$rule['id'],(int)$rule['current_version_id'],'earning',$sourceType,$sourcePublicId,(int)($source['source_amount_minor']??0),$amount,$rule['currency'],$idempotencyHash,$sourceHash,mg_creator_campaign_json_encode($snapshot),null,$actor]);
        $pdo->commit();
        return ['earning_event_id'=>$publicId,'amount_minor'=>$amount,'currency'=>$rule['currency']];
    }catch(PDOException $e){
        if($pdo->inTransaction())$pdo->rollBack();
        if((string)$e->getCode()==='23000'){
            $stmt=$pdo->prepare('SELECT public_id,amount_minor,currency FROM creator_campaign_earning_events WHERE campaign_id=? AND idempotency_hash=? LIMIT 1');
            $stmt->execute([(int)$source['campaign_id'],$idempotencyHash]);$row=$stmt->fetch(PDO::FETCH_ASSOC);
            if($row) return ['earning_event_id'=>$row['public_id'],'amount_minor'=>(int)$row['amount_minor'],'currency'=>$row['currency'],'idempotent'=>true];
        }
        throw $e;
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}

function mg_creator_campaign_compensation_adjust(PDO $pdo,array $user,string $participantPublicId,array $input): array
{
    $context=mg_creator_campaign_compensation_merchant_context($pdo,$user,'merchant.creator_compensation.manage');
    $idempotencyKey=trim((string)($input['idempotency_key']??''));
    if($idempotencyKey==='') throw new InvalidArgumentException('idempotency_key is required.');
    $amount=(int)($input['amount_minor']??0);
    if($amount===0) throw new InvalidArgumentException('amount_minor must be a non-zero integer.');
    $currency=mg_creator_campaign_compensation_currency($input['currency']??'USD');
    $reason=trim((string)($input['reason']??''));
    if($reason==='') throw new InvalidArgumentException('reason is required.');
    $idempotency=mg_creator_campaign_idempotency_hash($idempotencyKey);

    mg_creator_campaign_assert_transaction_boundary($pdo);
    $pdo->beginTransaction();
    try{
        $participant=mg_creator_campaign_compensation_participant($pdo,$participantPublicId,(int)$context['workspace_id'],null,true);
        $stmt=$pdo->prepare('SELECT public_id,amount_minor,currency FROM creator_campaign_earning_events WHERE campaign_id=? AND idempotency_hash=? LIMIT 1 FOR UPDATE');
        $stmt->execute([(int)$participant['campaign_id'],$idempotency]);
        $existing=$stmt->fetch(PDO::FETCH_ASSOC);
        if($existing){
            $pdo->commit();
            return ['earning_event_id'=>$existing['public_id'],'amount_minor'=>(int)$existing['amount_minor'],'currency'=>$existing['currency'],'idempotent'=>true];
        }
        $sourcePublicId='manual:'.hash('sha256',$participantPublicId.'|'.$idempotency);
        $publicId=mg_creator_campaign_public_id('ccee');
        $pdo->prepare('INSERT INTO creator_campaign_earning_events(public_id,campaign_id,participant_id,creator_user_id,agreement_version_id,event_type,source_type,source_public_id,amount_minor,currency,idempotency_hash,source_hash,calculation_snapshot_json,reason,created_by_user_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([$publicId,(int)$participant['campaign_id'],(int)$participant['id'],(int)$participant['creator_user_id'],(int)$participant['latest_accepted_version_id'],'adjustment','manual',$sourcePublicId,$amount,$currency,$idempotency,hash('sha256',$sourcePublicId),mg_creator_campaign_json_encode(['manual_adjustment'=>true,'amount_minor'=>$amount,'currency'=>$currency]),mb_substr($reason,0,2000),(int)$context['actor_user_id']]);
        $pdo->commit();
        return ['earning_event_id'=>$publicId,'amount_minor'=>$amount,'currency'=>$currency,'idempotent'=>false];
    }catch(PDOException $e){
        if($pdo->inTransaction())$pdo->rollBack();
        if((string)$e->getCode()==='23000'){
            $participant=mg_creator_campaign_compensation_participant($pdo,$participantPublicId,(int)$context['workspace_id'],null,false);
            $stmt=$pdo->prepare('SELECT public_id,amount_minor,currency FROM creator_campaign_earning_events WHERE campaign_id=? AND idempotency_hash=? LIMIT 1');
            $stmt->execute([(int)$participant['campaign_id'],$idempotency]);
            $existing=$stmt->fetch(PDO::FETCH_ASSOC);
            if($existing) return ['earning_event_id'=>$existing['public_id'],'amount_minor'=>(int)$existing['amount_minor'],'currency'=>$existing['currency'],'idempotent'=>true];
        }
        throw $e;
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}

function mg_creator_campaign_compensation_reverse(PDO $pdo,array $user,string $earningPublicId,array $input): array
{
    $context=mg_creator_campaign_compensation_merchant_context($pdo,$user,'merchant.creator_compensation.manage');
    mg_creator_campaign_assert_transaction_boundary($pdo);$pdo->beginTransaction();
    try{
        $event=mg_creator_campaign_earning_event($pdo,$earningPublicId,(int)$context['workspace_id'],true);
        if((string)$event['event_type']==='reversal') throw new DomainException('A reversal event cannot be reversed.');
        $reason=trim((string)($input['reason']??''));
        if($reason==='') throw new InvalidArgumentException('reason is required.');
        $key=mg_creator_campaign_idempotency_hash('reversal:'.$earningPublicId);
        $stmt=$pdo->prepare('SELECT public_id,amount_minor,currency FROM creator_campaign_earning_events WHERE reversal_of_event_id=? OR (campaign_id=? AND idempotency_hash=?) ORDER BY id ASC LIMIT 1 FOR UPDATE');
        $stmt->execute([(int)$event['id'],(int)$event['campaign_id'],$key]);
        $existing=$stmt->fetch(PDO::FETCH_ASSOC);
        if($existing){
            $pdo->commit();
            return ['earning_event_id'=>$existing['public_id'],'reversal_of'=>$earningPublicId,'amount_minor'=>(int)$existing['amount_minor'],'currency'=>$existing['currency'],'idempotent'=>true];
        }
        $publicId=mg_creator_campaign_public_id('ccee');
        $snapshot=['reversal_of'=>$earningPublicId,'original_amount_minor'=>(int)$event['amount_minor']];
        $pdo->prepare('INSERT INTO creator_campaign_earning_events(public_id,campaign_id,participant_id,creator_user_id,agreement_version_id,rule_id,rule_version_id,event_type,source_type,source_public_id,source_amount_minor,amount_minor,currency,reversal_of_event_id,idempotency_hash,source_hash,calculation_snapshot_json,reason,created_by_user_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([$publicId,(int)$event['campaign_id'],(int)$event['participant_id'],(int)$event['creator_user_id'],(int)$event['agreement_version_id'],$event['rule_id'],$event['rule_version_id'],'reversal',(string)$event['source_type'],'reversal:'.$earningPublicId,$event['source_amount_minor'],-1*(int)$event['amount_minor'],(string)$event['currency'],(int)$event['id'],$key,hash('sha256','reversal|'.$event['source_hash']),mg_creator_campaign_json_encode($snapshot),mb_substr($reason,0,2000),(int)$context['actor_user_id']]);
        $pdo->commit();return ['earning_event_id'=>$publicId,'reversal_of'=>$earningPublicId,'amount_minor'=>-1*(int)$event['amount_minor'],'currency'=>(string)$event['currency'],'idempotent'=>false];
    }catch(PDOException $e){
        if($pdo->inTransaction())$pdo->rollBack();
        if((string)$e->getCode()==='23000'){
            $event=mg_creator_campaign_earning_event($pdo,$earningPublicId,(int)$context['workspace_id'],false);
            $stmt=$pdo->prepare('SELECT public_id,amount_minor,currency FROM creator_campaign_earning_events WHERE reversal_of_event_id=? LIMIT 1');
            $stmt->execute([(int)$event['id']]);
            $existing=$stmt->fetch(PDO::FETCH_ASSOC);
            if($existing) return ['earning_event_id'=>$existing['public_id'],'reversal_of'=>$earningPublicId,'amount_minor'=>(int)$existing['amount_minor'],'currency'=>$existing['currency'],'idempotent'=>true];
        }
        throw $e;
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
