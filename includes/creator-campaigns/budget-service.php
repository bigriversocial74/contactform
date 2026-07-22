<?php
declare(strict_types=1);

function mg_creator_campaign_budget_save(PDO $pdo,array $user,string $campaignPublicId,array $input): array
{
    $context=mg_creator_campaign_budget_merchant_context($pdo,$user,'merchant.creator_budgets.manage');
    mg_creator_campaign_assert_transaction_boundary($pdo);$pdo->beginTransaction();
    try{
        $campaign=mg_creator_campaign_compensation_campaign($pdo,$campaignPublicId,(int)$context['workspace_id'],true);
        $currency=mg_creator_campaign_budget_currency($input['currency']??'USD');
        $limit=mg_creator_campaign_budget_minor($input['limit_minor']??null,'limit_minor');
        if($limit<1) throw new InvalidArgumentException('limit_minor must be positive.');
        $warning=(int)($input['warning_threshold_bps']??8000);
        if($warning<0||$warning>10000) throw new InvalidArgumentException('warning_threshold_bps must be between 0 and 10000.');
        $status=(string)($input['status']??'active');
        if(!in_array($status,['draft','active','paused','closed'],true)) throw new InvalidArgumentException('status is invalid.');
        $allowOverage=filter_var($input['allow_overage']??false,FILTER_VALIDATE_BOOLEAN)?1:0;
        $budget=mg_creator_campaign_budget_for_campaign($pdo,(int)$campaign['id'],$currency,true);
        if(!$budget){
            $publicId=mg_creator_campaign_public_id('ccbu');
            $pdo->prepare('INSERT INTO creator_campaign_budgets(public_id,campaign_id,currency,status,limit_minor,warning_threshold_bps,allow_overage,created_by_user_id,updated_by_user_id) VALUES (?,?,?,?,?,?,?,?,?)')
                ->execute([$publicId,(int)$campaign['id'],$currency,$status,$limit,$warning,$allowOverage,(int)$context['actor_user_id'],(int)$context['actor_user_id']]);
            $budget=['id'=>(int)$pdo->lastInsertId(),'public_id'=>$publicId,'campaign_id'=>(int)$campaign['id'],'allow_overage'=>$allowOverage,'limit_minor'=>$limit];
            $event=mg_creator_campaign_budget_append_event($pdo,$budget,'allocation',$limit,0,0,'budget:create:'.$publicId,(int)$context['actor_user_id'],null,null,'Initial campaign budget allocation.');
        }else{
            $publicId=(string)$budget['public_id'];$balances=mg_creator_campaign_budget_balances($pdo,(int)$budget['id']);
            if($status==='closed'&&$balances['reserved_minor']!==0) throw new DomainException('A budget with active reservations cannot be closed.');
            $delta=$limit-(int)$budget['limit_minor'];
            $budget['allow_overage']=$allowOverage;
            if($delta!==0){
                $event=mg_creator_campaign_budget_append_event($pdo,$budget,'allocation_adjustment',$delta,0,0,'budget:limit:'.$publicId.':'.$limit,(int)$context['actor_user_id'],null,null,'Campaign budget limit adjusted.');
            }else{$event=['balances'=>$balances];}
            $pdo->prepare('UPDATE creator_campaign_budgets SET status=?,limit_minor=?,warning_threshold_bps=?,allow_overage=?,updated_by_user_id=?,lock_version=lock_version+1 WHERE id=?')
                ->execute([$status,$limit,$warning,$allowOverage,(int)$context['actor_user_id'],(int)$budget['id']]);
        }
        $pdo->commit();return ['budget_id'=>$publicId,'balances'=>$event['balances']];
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}

function mg_creator_campaign_budget_reserve_earning(PDO $pdo,array $user,string $earningPublicId,array $input=[]): array
{
    $context=mg_creator_campaign_budget_merchant_context($pdo,$user,'merchant.creator_budgets.manage');
    mg_creator_campaign_assert_transaction_boundary($pdo);$pdo->beginTransaction();
    try{
        $earning=mg_creator_campaign_budget_earning($pdo,$earningPublicId,(int)$context['workspace_id']);
        $amount=(int)$earning['amount_minor'];
        if($amount<1||!in_array((string)$earning['event_type'],['earning','adjustment'],true)) throw new DomainException('Only positive earning or adjustment events can be reserved.');
        $budget=mg_creator_campaign_budget_for_campaign($pdo,(int)$earning['campaign_id'],(string)$earning['currency'],true);
        if(!$budget||$budget['status']!=='active') throw new DomainException('An active matching campaign budget is required.');
        $stmt=$pdo->prepare('SELECT public_id,status FROM creator_campaign_budget_reservations WHERE earning_event_id=? LIMIT 1 FOR UPDATE');$stmt->execute([(int)$earning['id']]);$existing=$stmt->fetch(PDO::FETCH_ASSOC);
        if($existing){$pdo->commit();return ['reservation_id'=>$existing['public_id'],'status'=>$existing['status'],'idempotent'=>true];}
        $key=trim((string)($input['idempotency_key']??'reserve:'.$earningPublicId));if($key==='')throw new InvalidArgumentException('idempotency_key is required.');
        $publicId=mg_creator_campaign_public_id('ccbr');$hash=mg_creator_campaign_idempotency_hash($key);
        $pdo->prepare('INSERT INTO creator_campaign_budget_reservations(public_id,budget_id,campaign_id,earning_event_id,participant_id,creator_user_id,amount_minor,currency,status,idempotency_hash,reserved_at,created_by_user_id,updated_by_user_id) VALUES (?,?,?,?,?,?,?,?,?,?,NOW(),?,?)')
            ->execute([$publicId,(int)$budget['id'],(int)$earning['campaign_id'],(int)$earning['id'],(int)$earning['participant_id'],(int)$earning['creator_user_id'],$amount,(string)$earning['currency'],'reserved',$hash,(int)$context['actor_user_id'],(int)$context['actor_user_id']]);
        $reservationId=(int)$pdo->lastInsertId();
        $event=mg_creator_campaign_budget_append_event($pdo,$budget,'reserve',-$amount,$amount,0,'reserve-event:'.$earningPublicId,(int)$context['actor_user_id'],$reservationId,(int)$earning['id'],'Creator earning reserved against campaign budget.');
        $pdo->commit();return ['reservation_id'=>$publicId,'status'=>'reserved','balances'=>$event['balances']];
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}

function mg_creator_campaign_budget_transition(PDO $pdo,array $user,string $reservationPublicId,string $action,array $input=[]): array
{
    $context=mg_creator_campaign_budget_merchant_context($pdo,$user,'merchant.creator_budgets.manage');
    if(!in_array($action,['commit','release'],true)) throw new InvalidArgumentException('Budget reservation action is invalid.');
    mg_creator_campaign_assert_transaction_boundary($pdo);$pdo->beginTransaction();
    try{
        $reservation=mg_creator_campaign_budget_reservation($pdo,$reservationPublicId,(int)$context['workspace_id'],true);
        $budget=mg_creator_campaign_budget_by_public_id($pdo,(string)$reservation['budget_public_id'],(int)$context['workspace_id'],true);
        $amount=(int)$reservation['amount_minor'];$from=(string)$reservation['status'];$reason=mb_substr(trim((string)($input['reason']??'')),0,2000);
        if($action==='commit'){
            if($from==='committed'){$pdo->commit();return ['reservation_id'=>$reservationPublicId,'status'=>'committed','idempotent'=>true];}
            if($from!=='reserved') throw new DomainException('Only reserved budget obligations can be committed.');
            $event=mg_creator_campaign_budget_append_event($pdo,$budget,'commit',0,-$amount,$amount,'commit:'.$reservationPublicId,(int)$context['actor_user_id'],(int)$reservation['id'],(int)$reservation['earning_event_id'],$reason?:'Creator earning obligation committed.');
            $pdo->prepare("UPDATE creator_campaign_budget_reservations SET status='committed',committed_at=NOW(),reason=?,updated_by_user_id=?,lock_version=lock_version+1 WHERE id=?")->execute([$reason?:null,(int)$context['actor_user_id'],(int)$reservation['id']]);$status='committed';
        }else{
            if(in_array($from,['released','cancelled'],true)){$pdo->commit();return ['reservation_id'=>$reservationPublicId,'status'=>$from,'idempotent'=>true];}
            if($from==='reserved'){$event=mg_creator_campaign_budget_append_event($pdo,$budget,'release',$amount,-$amount,0,'release:'.$reservationPublicId,(int)$context['actor_user_id'],(int)$reservation['id'],(int)$reservation['earning_event_id'],$reason?:'Reserved budget released.');}
            elseif($from==='committed'){$event=mg_creator_campaign_budget_append_event($pdo,$budget,'restore',$amount,0,-$amount,'restore:'.$reservationPublicId,(int)$context['actor_user_id'],(int)$reservation['id'],(int)$reservation['earning_event_id'],$reason?:'Committed budget obligation restored.');}
            else throw new DomainException('This budget reservation cannot be released.');
            $pdo->prepare("UPDATE creator_campaign_budget_reservations SET status='released',released_at=NOW(),reason=?,updated_by_user_id=?,lock_version=lock_version+1 WHERE id=?")->execute([$reason?:null,(int)$context['actor_user_id'],(int)$reservation['id']]);$status='released';
        }
        $pdo->commit();return ['reservation_id'=>$reservationPublicId,'status'=>$status,'balances'=>$event['balances']];
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}

function mg_creator_campaign_budget_sync_earnings(PDO $pdo,array $user,string $campaignPublicId): array
{
    $context=mg_creator_campaign_budget_merchant_context($pdo,$user,'merchant.creator_budgets.manage');
    $campaign=mg_creator_campaign_compensation_campaign($pdo,$campaignPublicId,(int)$context['workspace_id'],false);
    $stmt=$pdo->prepare("SELECT e.public_id FROM creator_campaign_earning_events e LEFT JOIN creator_campaign_budget_reservations r ON r.earning_event_id=e.id WHERE e.campaign_id=? AND e.amount_minor>0 AND e.event_type IN ('earning','adjustment') AND r.id IS NULL ORDER BY e.id ASC LIMIT 100");
    $stmt->execute([(int)$campaign['id']]);$created=[];$errors=[];
    foreach($stmt->fetchAll(PDO::FETCH_COLUMN)?:[] as $earningId){try{$created[]=mg_creator_campaign_budget_reserve_earning($pdo,$user,(string)$earningId);}catch(Throwable $e){$errors[]=['earning_event_id'=>$earningId,'message'=>$e->getMessage()];}}
    return ['created'=>$created,'errors'=>$errors,'created_count'=>count($created),'error_count'=>count($errors)];
}
