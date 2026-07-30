<?php
declare(strict_types=1);

/**
 * Creator affiliate merchant operations.
 *
 * This layer is intentionally provider-neutral. It defines and enforces the
 * merchant payout operating policy, surfaces reconciliation exceptions, and
 * provides guided readiness data without initiating external money movement.
 */

function mg_creator_campaign_operations_required_tables(): array
{
    return ['creator_campaign_payout_policies','creator_campaign_reconciliation_cases'];
}

function mg_creator_campaign_operations_installed(PDO $pdo): bool
{
    $tables=mg_creator_campaign_operations_required_tables();
    $marks=implode(',',array_fill(0,count($tables),'?'));
    $stmt=$pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ({$marks})");
    $stmt->execute($tables);
    return (int)$stmt->fetchColumn()===count($tables);
}

function mg_creator_campaign_operations_assert_installed(PDO $pdo): void
{
    if(!mg_creator_campaign_operations_installed($pdo)){
        throw new RuntimeException('Creator affiliate operations schema is incomplete.');
    }
}

function mg_creator_campaign_operations_currency(mixed $value): string
{
    return mg_creator_campaign_compensation_currency($value??'USD');
}

function mg_creator_campaign_operations_default_policy(string $currency='USD'): array
{
    return [
        'public_id'=>null,
        'currency'=>mg_creator_campaign_operations_currency($currency),
        'status'=>'active',
        'cadence'=>'manual',
        'payout_weekday'=>null,
        'payout_day_of_month'=>null,
        'hold_days'=>7,
        'minimum_payout_minor'=>2500,
        'method_label'=>'External provider or merchant-approved payment method',
        'payment_instructions'=>'Payouts require merchant approval and an externally confirmed provider reference before they are marked paid.',
        'dispute_window_days'=>30,
        'manual_approval_required'=>1,
        'configured'=>false,
        'next_payout_date'=>null,
    ];
}

function mg_creator_campaign_operations_policy(PDO $pdo,int $workspaceId,string $currency='USD',bool $forUpdate=false): ?array
{
    mg_creator_campaign_operations_assert_installed($pdo);
    $currency=mg_creator_campaign_operations_currency($currency);
    $sql='SELECT * FROM creator_campaign_payout_policies WHERE workspace_id=? AND currency=? LIMIT 1'.($forUpdate?' FOR UPDATE':'');
    $stmt=$pdo->prepare($sql);
    $stmt->execute([$workspaceId,$currency]);
    $row=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$row)return null;
    $row['configured']=true;
    $row['next_payout_date']=mg_creator_campaign_operations_next_payout_date($row);
    return $row;
}

function mg_creator_campaign_operations_effective_policy(PDO $pdo,int $workspaceId,string $currency='USD'): array
{
    $policy=mg_creator_campaign_operations_policy($pdo,$workspaceId,$currency,false);
    return $policy?:mg_creator_campaign_operations_default_policy($currency);
}

function mg_creator_campaign_operations_next_payout_date(array $policy,?DateTimeImmutable $now=null): ?string
{
    if(($policy['status']??'active')!=='active')return null;
    $cadence=(string)($policy['cadence']??'manual');
    if($cadence==='manual')return null;
    $now=$now?:new DateTimeImmutable('now',new DateTimeZone('UTC'));
    if(in_array($cadence,['weekly','biweekly'],true)){
        $weekday=max(1,min(7,(int)($policy['payout_weekday']??5)));
        $today=(int)$now->format('N');
        $days=($weekday-$today+7)%7;
        if($days===0)$days=$cadence==='biweekly'?14:7;
        if($cadence==='biweekly'&&$days<7)$days+=7;
        return $now->modify('+'.$days.' days')->format('Y-m-d');
    }
    $day=max(1,min(28,(int)($policy['payout_day_of_month']??15)));
    $candidate=$now->setDate((int)$now->format('Y'),(int)$now->format('m'),$day)->setTime(0,0);
    if($candidate<=$now)$candidate=$candidate->modify('first day of next month')->setDate((int)$candidate->format('Y'),(int)$candidate->format('m'),$day);
    return $candidate->format('Y-m-d');
}

function mg_creator_campaign_operations_policy_save(PDO $pdo,array $user,array $input): array
{
    $context=mg_creator_campaign_payout_merchant_context($pdo,$user,'merchant.creator_payouts.manage');
    mg_creator_campaign_operations_assert_installed($pdo);
    $workspaceId=(int)$context['workspace_id'];
    $currency=mg_creator_campaign_operations_currency($input['currency']??'USD');
    $status=strtolower(trim((string)($input['status']??'active')));
    $cadence=strtolower(trim((string)($input['cadence']??'manual')));
    if(!in_array($status,['active','paused'],true))throw new InvalidArgumentException('Payout policy status is invalid.');
    if(!in_array($cadence,['manual','weekly','biweekly','monthly'],true))throw new InvalidArgumentException('Payout cadence is invalid.');
    $weekday=$input['payout_weekday']??null;
    $monthDay=$input['payout_day_of_month']??null;
    $weekday=$weekday===''||$weekday===null?null:(int)$weekday;
    $monthDay=$monthDay===''||$monthDay===null?null:(int)$monthDay;
    if(in_array($cadence,['weekly','biweekly'],true)&&($weekday===null||$weekday<1||$weekday>7))throw new InvalidArgumentException('A payout weekday from 1 through 7 is required.');
    if($cadence==='monthly'&&($monthDay===null||$monthDay<1||$monthDay>28))throw new InvalidArgumentException('A payout day from 1 through 28 is required.');
    if($cadence==='manual'){$weekday=null;$monthDay=null;}
    if(in_array($cadence,['weekly','biweekly'],true))$monthDay=null;
    if($cadence==='monthly')$weekday=null;
    $holdDays=(int)($input['hold_days']??7);
    $minimum=mg_creator_campaign_compensation_minor($input['minimum_payout_minor']??2500,'minimum_payout_minor',false);
    $disputeWindow=(int)($input['dispute_window_days']??30);
    if($holdDays<0||$holdDays>90)throw new InvalidArgumentException('Hold days must be between 0 and 90.');
    if($disputeWindow<0||$disputeWindow>120)throw new InvalidArgumentException('Dispute window must be between 0 and 120 days.');
    $method=mb_substr(trim((string)($input['method_label']??'')),0,120);
    $instructions=mb_substr(trim((string)($input['payment_instructions']??'')),0,2000);
    $actor=(int)$context['actor_user_id'];
    mg_creator_campaign_assert_transaction_boundary($pdo);
    $pdo->beginTransaction();
    try{
        $existing=mg_creator_campaign_operations_policy($pdo,$workspaceId,$currency,true);
        if($existing){
            $pdo->prepare('UPDATE creator_campaign_payout_policies SET status=?,cadence=?,payout_weekday=?,payout_day_of_month=?,hold_days=?,minimum_payout_minor=?,method_label=?,payment_instructions=?,dispute_window_days=?,manual_approval_required=1,updated_by_user_id=?,lock_version=lock_version+1 WHERE id=?')
                ->execute([$status,$cadence,$weekday,$monthDay,$holdDays,$minimum,$method?:null,$instructions?:null,$disputeWindow,$actor,(int)$existing['id']]);
        }else{
            $pdo->prepare('INSERT INTO creator_campaign_payout_policies(public_id,workspace_id,currency,status,cadence,payout_weekday,payout_day_of_month,hold_days,minimum_payout_minor,method_label,payment_instructions,dispute_window_days,manual_approval_required,created_by_user_id,updated_by_user_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,1,?,?)')
                ->execute([mg_creator_campaign_public_id('ccpy'),$workspaceId,$currency,$status,$cadence,$weekday,$monthDay,$holdDays,$minimum,$method?:null,$instructions?:null,$disputeWindow,$actor,$actor]);
        }
        $policy=mg_creator_campaign_operations_policy($pdo,$workspaceId,$currency,false);
        $pdo->commit();
        return $policy?:mg_creator_campaign_operations_default_policy($currency);
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}

function mg_creator_campaign_operations_campaign_readiness(PDO $pdo,int $workspaceId): array
{
    $stmt=$pdo->prepare("SELECT cc.public_id,cc.title,cc.status,cc.updated_at,
      (SELECT COUNT(*) FROM creator_campaign_products ccp WHERE ccp.campaign_id=cc.id AND ccp.relationship_type IN('primary','featured','commissionable')) eligible_products,
      (SELECT COUNT(*) FROM creator_campaign_compensation_rules r WHERE r.campaign_id=cc.id AND r.trigger_type='purchase_attributed' AND r.status='active') active_purchase_rules,
      (SELECT COUNT(*) FROM creator_campaign_budgets b WHERE b.campaign_id=cc.id AND b.status='active') active_budgets,
      (SELECT COUNT(*) FROM creator_campaign_participants p WHERE p.campaign_id=cc.id AND p.status='active') active_creators,
      (SELECT COUNT(*) FROM creator_campaign_tracking_sources s WHERE s.campaign_id=cc.id AND s.status='active') active_tracking_sources
      FROM creator_campaigns cc WHERE cc.workspace_id=? ORDER BY cc.updated_at DESC,cc.id DESC LIMIT 200");
    $stmt->execute([$workspaceId]);
    $rows=$stmt->fetchAll(PDO::FETCH_ASSOC)?:[];
    foreach($rows as &$row){
        $checks=[
            ['key'=>'lifecycle','label'=>'Campaign scheduled or active','ok'=>in_array((string)$row['status'],['scheduled','active'],true),'weight'=>15,'href'=>'/merchant-creator-campaign-builder.php?campaign='.rawurlencode((string)$row['public_id'])],
            ['key'=>'products','label'=>'Commissionable product selected','ok'=>(int)$row['eligible_products']>0,'weight'=>20,'href'=>'/merchant-creator-campaign-builder.php?campaign='.rawurlencode((string)$row['public_id'])],
            ['key'=>'compensation','label'=>'Active purchase commission rule','ok'=>(int)$row['active_purchase_rules']>0,'weight'=>20,'href'=>'/merchant-creator-compensation.php?campaign='.rawurlencode((string)$row['public_id'])],
            ['key'=>'budget','label'=>'Active funded budget','ok'=>(int)$row['active_budgets']>0,'weight'=>20,'href'=>'/merchant-creator-budgets.php?campaign='.rawurlencode((string)$row['public_id'])],
            ['key'=>'creators','label'=>'Approved active Creator','ok'=>(int)$row['active_creators']>0,'weight'=>15,'href'=>'/merchant-creator-participation.php?campaign='.rawurlencode((string)$row['public_id'])],
            ['key'=>'tracking','label'=>'Active Creator tracking source','ok'=>(int)$row['active_tracking_sources']>0,'weight'=>10,'href'=>'/merchant-creator-tracking.php?campaign='.rawurlencode((string)$row['public_id'])],
        ];
        $score=0;$missing=[];
        foreach($checks as $check){if($check['ok'])$score+=(int)$check['weight'];else$missing[]=$check;}
        $row['readiness_score']=$score;
        $row['ready']=$score===100;
        $row['checks']=$checks;
        $row['missing']=$missing;
    }
    unset($row);
    return $rows;
}

function mg_creator_campaign_operations_participants(PDO $pdo,int $workspaceId,string $currency): array
{
    $stmt=$pdo->prepare("SELECT p.public_id participant_public_id,p.status participant_status,p.creator_user_id,
      cc.public_id campaign_public_id,cc.title campaign_title,cp.display_name creator_name,
      COALESCE(pp.status,'incomplete') payout_profile_status,pp.method_label,COALESCE(pp.minimum_payout_minor,0) profile_minimum_payout_minor,
      COALESCE(SUM(CASE WHEN r.status='committed' AND r.currency=? THEN r.amount_minor ELSE 0 END),0) committed_minor,
      MIN(CASE WHEN r.status='committed' AND r.currency=? THEN r.committed_at ELSE NULL END) oldest_committed_at
      FROM creator_campaign_participants p
      INNER JOIN creator_campaigns cc ON cc.id=p.campaign_id
      INNER JOIN creator_profiles cp ON cp.id=p.creator_profile_id
      LEFT JOIN creator_campaign_payout_profiles pp ON pp.creator_user_id=p.creator_user_id AND pp.currency=?
      LEFT JOIN creator_campaign_budget_reservations r ON r.participant_id=p.id
      WHERE cc.workspace_id=?
      GROUP BY p.id,pp.id
      ORDER BY cc.updated_at DESC,cp.display_name ASC,p.id DESC LIMIT 500");
    $stmt->execute([$currency,$currency,$currency,$workspaceId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC)?:[];
}

function mg_creator_campaign_operations_candidate(string $type,string $severity,string $sourceType,string $sourcePublicId,string $summary,array $detail=[],?string $campaignPublicId=null): array
{
    return compact('type','severity','sourceType','sourcePublicId','summary','detail','campaignPublicId');
}

function mg_creator_campaign_operations_detect(PDO $pdo,int $workspaceId): array
{
    $candidates=[];$errors=[];
    $run=static function(string $name,callable $query)use(&$candidates,&$errors):void{
        try{$items=$query();foreach($items as $item)$candidates[]=$item;}catch(Throwable $e){$errors[]=['check'=>$name,'message'=>$e->getMessage()];}
    };

    $run('paid_order_state',function()use($pdo,$workspaceId):array{
        $stmt=$pdo->prepare("SELECT o.public_id order_public_id,cc.public_id campaign_public_id,cc.title campaign_title,
          JSON_UNQUOTE(JSON_EXTRACT(o.metadata_json,'$.creator_affiliate.status')) affiliate_status,
          JSON_UNQUOTE(JSON_EXTRACT(o.metadata_json,'$.creator_affiliate.last_error')) last_error
          FROM commerce_orders o
          INNER JOIN merchant_workspaces mw ON mw.merchant_user_id=o.merchant_user_id
          INNER JOIN creator_campaigns cc ON cc.workspace_id=mw.id AND cc.public_id=JSON_UNQUOTE(JSON_EXTRACT(o.metadata_json,'$.creator_affiliate.campaign_id'))
          WHERE cc.workspace_id=? AND o.payment_status IN('paid','partially_refunded','refunded')
          AND JSON_UNQUOTE(JSON_EXTRACT(o.metadata_json,'$.creator_affiliate.status')) IN('captured','attributed','earned_unreserved','failed')
          ORDER BY o.updated_at DESC LIMIT 300");
        $stmt->execute([$workspaceId]);$out=[];
        foreach($stmt->fetchAll(PDO::FETCH_ASSOC)?:[] as $row){$status=(string)$row['affiliate_status'];$severity=$status==='failed'?'critical':($status==='earned_unreserved'?'high':'warning');$out[]=mg_creator_campaign_operations_candidate('paid_order_incomplete',$severity,'order',(string)$row['order_public_id'],'Paid order has an incomplete Creator affiliate lifecycle.',['status'=>$status,'campaign_title'=>$row['campaign_title'],'last_error'=>$row['last_error']],(string)$row['campaign_public_id']);}
        return $out;
    });

    $run('attribution_without_earning',function()use($pdo,$workspaceId):array{
        $stmt=$pdo->prepare("SELECT a.public_id attribution_public_id,cc.public_id campaign_public_id,cc.title campaign_title,te.public_id conversion_public_id
          FROM creator_campaign_attributions a
          INNER JOIN creator_campaigns cc ON cc.id=a.campaign_id
          INNER JOIN creator_campaign_tracking_events te ON te.id=a.conversion_event_id AND te.event_type='purchase'
          LEFT JOIN creator_campaign_earning_events ee ON ee.campaign_id=a.campaign_id AND ee.source_public_id=a.public_id AND ee.event_type='earning'
          WHERE cc.workspace_id=? AND a.status IN('attributed','overridden') AND ee.id IS NULL
          ORDER BY a.attributed_at DESC LIMIT 300");
        $stmt->execute([$workspaceId]);$out=[];foreach($stmt->fetchAll(PDO::FETCH_ASSOC)?:[] as $row)$out[]=mg_creator_campaign_operations_candidate('attribution_missing_earning','critical','attribution',(string)$row['attribution_public_id'],'Attributed purchase has no Creator earning.',['conversion_id'=>$row['conversion_public_id'],'campaign_title'=>$row['campaign_title']],(string)$row['campaign_public_id']);return $out;
    });

    $run('earning_without_reservation',function()use($pdo,$workspaceId):array{
        $stmt=$pdo->prepare("SELECT e.public_id earning_public_id,e.amount_minor,e.currency,cc.public_id campaign_public_id,cc.title campaign_title
          FROM creator_campaign_earning_events e
          INNER JOIN creator_campaigns cc ON cc.id=e.campaign_id
          LEFT JOIN creator_campaign_budget_reservations r ON r.earning_event_id=e.id
          WHERE cc.workspace_id=? AND e.event_type='earning' AND e.amount_minor>0 AND e.source_type IN('attribution','conversion') AND r.id IS NULL
          AND e.amount_minor+COALESCE((SELECT SUM(adj.amount_minor) FROM creator_campaign_earning_events adj WHERE adj.event_type='adjustment' AND JSON_UNQUOTE(JSON_EXTRACT(adj.calculation_snapshot_json,'$.affiliate_refund.original_earning_event_id'))=e.public_id),0)>0
          ORDER BY e.created_at DESC LIMIT 300");
        $stmt->execute([$workspaceId]);$out=[];foreach($stmt->fetchAll(PDO::FETCH_ASSOC)?:[] as $row)$out[]=mg_creator_campaign_operations_candidate('earning_missing_reservation','high','earning',(string)$row['earning_public_id'],'Creator earning is not reserved against a campaign budget.',['amount_minor'=>(int)$row['amount_minor'],'currency'=>$row['currency'],'campaign_title'=>$row['campaign_title']],(string)$row['campaign_public_id']);return $out;
    });

    $run('refund_without_adjustment',function()use($pdo,$workspaceId):array{
        $stmt=$pdo->prepare("SELECT pr.public_id refund_public_id,pr.amount_cents,o.public_id order_public_id,cc.public_id campaign_public_id,cc.title campaign_title,
          JSON_UNQUOTE(JSON_EXTRACT(o.metadata_json,'$.creator_affiliate.earning_event_id')) earning_public_id
          FROM payment_refunds pr
          INNER JOIN commerce_orders o ON o.id=pr.order_id
          INNER JOIN merchant_workspaces mw ON mw.merchant_user_id=o.merchant_user_id
          INNER JOIN creator_campaigns cc ON cc.workspace_id=mw.id AND cc.public_id=JSON_UNQUOTE(JSON_EXTRACT(o.metadata_json,'$.creator_affiliate.campaign_id'))
          WHERE cc.workspace_id=? AND pr.status='succeeded'
          AND COALESCE(JSON_UNQUOTE(JSON_EXTRACT(o.metadata_json,'$.creator_affiliate.earning_event_id')),'')<>''
          AND NOT EXISTS(SELECT 1 FROM creator_campaign_earning_events adj WHERE adj.source_public_id=CONCAT('refund:',pr.public_id) AND adj.event_type='adjustment')
          ORDER BY pr.processed_at DESC,pr.id DESC LIMIT 300");
        $stmt->execute([$workspaceId]);$out=[];foreach($stmt->fetchAll(PDO::FETCH_ASSOC)?:[] as $row)$out[]=mg_creator_campaign_operations_candidate('refund_missing_adjustment','critical','refund',(string)$row['refund_public_id'],'Successful refund has no Creator commission adjustment.',['order_id'=>$row['order_public_id'],'earning_id'=>$row['earning_public_id'],'amount_minor'=>(int)$row['amount_cents'],'campaign_title'=>$row['campaign_title']],(string)$row['campaign_public_id']);return $out;
    });

    $run('payout_attention',function()use($pdo,$workspaceId):array{
        $stmt=$pdo->prepare("SELECT p.public_id,p.status,p.provider_reference,p.updated_at,cc.public_id campaign_public_id,cc.title campaign_title
          FROM creator_campaign_payouts p INNER JOIN creator_campaigns cc ON cc.id=p.campaign_id
          WHERE cc.workspace_id=? AND (p.status='failed' OR (p.status='processing' AND p.updated_at<DATE_SUB(UTC_TIMESTAMP(),INTERVAL 3 DAY)) OR (p.status='paid' AND COALESCE(p.provider_reference,'')=''))
          ORDER BY p.updated_at ASC LIMIT 300");
        $stmt->execute([$workspaceId]);$out=[];foreach($stmt->fetchAll(PDO::FETCH_ASSOC)?:[] as $row){$status=(string)$row['status'];$severity=$status==='paid'?'critical':'high';$out[]=mg_creator_campaign_operations_candidate('payout_needs_attention',$severity,'payout',(string)$row['public_id'],'Creator payout requires operator attention.',['status'=>$status,'provider_reference'=>$row['provider_reference'],'updated_at'=>$row['updated_at'],'campaign_title'=>$row['campaign_title']],(string)$row['campaign_public_id']);}return $out;
    });

    $run('active_disputes',function()use($pdo,$workspaceId):array{
        $stmt=$pdo->prepare("SELECT d.public_id,d.status,d.source_type,d.source_public_id,d.reason,cc.public_id campaign_public_id,cc.title campaign_title
          FROM creator_campaign_disputes d INNER JOIN creator_campaigns cc ON cc.id=d.campaign_id
          WHERE cc.workspace_id=? AND d.status IN('open','under_review') ORDER BY d.updated_at ASC LIMIT 300");
        $stmt->execute([$workspaceId]);$out=[];foreach($stmt->fetchAll(PDO::FETCH_ASSOC)?:[] as $row)$out[]=mg_creator_campaign_operations_candidate('active_dispute','warning','dispute',(string)$row['public_id'],'Creator finance dispute is awaiting resolution.',['status'=>$row['status'],'source_type'=>$row['source_type'],'source_public_id'=>$row['source_public_id'],'reason'=>$row['reason'],'campaign_title'=>$row['campaign_title']],(string)$row['campaign_public_id']);return $out;
    });

    $run('suspect_tracking',function()use($pdo,$workspaceId):array{
        $stmt=$pdo->prepare("SELECT cc.public_id campaign_public_id,cc.title campaign_title,COUNT(*) suspect_count
          FROM creator_campaign_tracking_events te INNER JOIN creator_campaigns cc ON cc.id=te.campaign_id
          WHERE cc.workspace_id=? AND te.status='suspect' AND te.occurred_at>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 30 DAY)
          GROUP BY cc.id HAVING COUNT(*)>=3 ORDER BY suspect_count DESC LIMIT 100");
        $stmt->execute([$workspaceId]);$out=[];foreach($stmt->fetchAll(PDO::FETCH_ASSOC)?:[] as $row)$out[]=mg_creator_campaign_operations_candidate('suspect_tracking_activity','warning','campaign',(string)$row['campaign_public_id'],'Campaign has repeated suspect Creator tracking activity.',['suspect_count'=>(int)$row['suspect_count'],'campaign_title'=>$row['campaign_title']],(string)$row['campaign_public_id']);return $out;
    });

    return ['candidates'=>$candidates,'errors'=>$errors];
}

function mg_creator_campaign_operations_scan(PDO $pdo,array $user): array
{
    $context=mg_creator_campaign_payout_merchant_context($pdo,$user,'merchant.creator_payouts.manage');
    mg_creator_campaign_operations_assert_installed($pdo);
    $workspaceId=(int)$context['workspace_id'];$actor=(int)$context['actor_user_id'];$scanToken=bin2hex(random_bytes(16));
    $detected=mg_creator_campaign_operations_detect($pdo,$workspaceId);
    mg_creator_campaign_assert_transaction_boundary($pdo);$pdo->beginTransaction();
    try{
        foreach($detected['candidates'] as $candidate){
            $fingerprint=hash('sha256',$candidate['type'].'|'.$candidate['sourceType'].'|'.$candidate['sourcePublicId']);
            $pdo->prepare("INSERT INTO creator_campaign_reconciliation_cases(public_id,workspace_id,fingerprint,issue_type,severity,source_type,source_public_id,campaign_public_id,status,summary,detail_json,scan_token,first_seen_at,last_seen_at)
              VALUES (?,?,?,?,?,?,?,?, 'open',?,?,?,NOW(),NOW())
              ON DUPLICATE KEY UPDATE severity=VALUES(severity),campaign_public_id=VALUES(campaign_public_id),summary=VALUES(summary),detail_json=VALUES(detail_json),scan_token=VALUES(scan_token),last_seen_at=NOW(),status=IF(status='resolved','open',status),resolved_at=IF(status='resolved',NULL,resolved_at)")
                ->execute([mg_creator_campaign_public_id('ccrc'),$workspaceId,$fingerprint,$candidate['type'],$candidate['severity'],$candidate['sourceType'],$candidate['sourcePublicId'],$candidate['campaignPublicId'], $candidate['summary'],mg_creator_campaign_json_encode($candidate['detail']),$scanToken]);
        }
        if($detected['errors']===[]){
            $pdo->prepare("UPDATE creator_campaign_reconciliation_cases SET status='resolved',resolved_at=NOW(),updated_at=NOW() WHERE workspace_id=? AND status IN('open','acknowledged') AND COALESCE(scan_token,'')<>?")->execute([$workspaceId,$scanToken]);
        }
        if($detected['errors']!==[]){
            $fingerprint=hash('sha256','scan_error|workspace|'.$workspaceId);
            $pdo->prepare("INSERT INTO creator_campaign_reconciliation_cases(public_id,workspace_id,fingerprint,issue_type,severity,source_type,source_public_id,status,summary,detail_json,scan_token,first_seen_at,last_seen_at)
              VALUES (?,?,?,?,?,'workspace',?,'open',?,?,?,NOW(),NOW())
              ON DUPLICATE KEY UPDATE severity='critical',summary=VALUES(summary),detail_json=VALUES(detail_json),scan_token=VALUES(scan_token),last_seen_at=NOW(),status='open',resolved_at=NULL")
                ->execute([mg_creator_campaign_public_id('ccrc'),$workspaceId,$fingerprint,'reconciliation_scan_error','critical',(string)$workspaceId,'One or more affiliate reconciliation checks could not run.',mg_creator_campaign_json_encode($detected['errors']),$scanToken]);
        }
        $pdo->commit();
        return ['detected'=>count($detected['candidates']),'errors'=>$detected['errors'],'scan_token'=>$scanToken,'actor_user_id'=>$actor];
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}

function mg_creator_campaign_operations_cases(PDO $pdo,int $workspaceId,array $filters=[]): array
{
    $status=strtolower(trim((string)($filters['status']??'open')));$allowed=['open','acknowledged','resolved','ignored','all'];if(!in_array($status,$allowed,true))$status='open';
    $sql='SELECT * FROM creator_campaign_reconciliation_cases WHERE workspace_id=?';$params=[$workspaceId];
    if($status!=='all'){$sql.=' AND status=?';$params[]=$status;}
    $sql.=" ORDER BY FIELD(severity,'critical','high','warning','info'),last_seen_at DESC,id DESC LIMIT 500";
    $stmt=$pdo->prepare($sql);$stmt->execute($params);$rows=$stmt->fetchAll(PDO::FETCH_ASSOC)?:[];
    foreach($rows as &$row){$row['detail']=mg_creator_campaign_participation_decode_json($row['detail_json']??null);unset($row['detail_json'],$row['scan_token'],$row['fingerprint']);}unset($row);
    return $rows;
}

function mg_creator_campaign_operations_case_update(PDO $pdo,array $user,string $casePublicId,array $input): array
{
    $context=mg_creator_campaign_payout_merchant_context($pdo,$user,'merchant.creator_payouts.manage');mg_creator_campaign_operations_assert_installed($pdo);
    $status=strtolower(trim((string)($input['status']??'')));if(!in_array($status,['open','acknowledged','resolved','ignored'],true))throw new InvalidArgumentException('Reconciliation case status is invalid.');
    $note=mb_substr(trim((string)($input['operator_note']??'')),0,2000);$actor=(int)$context['actor_user_id'];
    $stmt=$pdo->prepare('SELECT id,status FROM creator_campaign_reconciliation_cases WHERE public_id=? AND workspace_id=? LIMIT 1');$stmt->execute([$casePublicId,(int)$context['workspace_id']]);$case=$stmt->fetch(PDO::FETCH_ASSOC);if(!$case)throw new RuntimeException('Reconciliation case not found.');
    $ack=$status==='acknowledged'?'NOW()':'acknowledged_at';$resolved=$status==='resolved'?'NOW()':($status==='open'?'NULL':'resolved_at');
    $pdo->prepare("UPDATE creator_campaign_reconciliation_cases SET status=?,operator_note=?,assigned_user_id=?,acknowledged_at={$ack},resolved_at={$resolved},updated_at=NOW() WHERE id=?")
        ->execute([$status,$note?:null,$status==='open'?null:$actor,(int)$case['id']]);
    return ['case_id'=>$casePublicId,'status'=>$status];
}

function mg_creator_campaign_operations_creator_policies(PDO $pdo,int $creatorUserId): array
{
    if(!mg_creator_campaign_operations_installed($pdo))return [];
    $stmt=$pdo->prepare("SELECT DISTINCT py.public_id,py.workspace_id,py.currency,py.status,py.cadence,py.payout_weekday,py.payout_day_of_month,py.hold_days,py.minimum_payout_minor,py.method_label,py.payment_instructions,py.dispute_window_days
      FROM creator_campaign_payout_policies py
      INNER JOIN creator_campaigns cc ON cc.workspace_id=py.workspace_id
      INNER JOIN creator_campaign_participants p ON p.campaign_id=cc.id AND p.creator_user_id=?
      ORDER BY py.currency,py.updated_at DESC");
    $stmt->execute([$creatorUserId]);$rows=$stmt->fetchAll(PDO::FETCH_ASSOC)?:[];foreach($rows as &$row)$row['next_payout_date']=mg_creator_campaign_operations_next_payout_date($row);unset($row);return $rows;
}

function mg_creator_campaign_operations_dashboard(PDO $pdo,array $user,array $filters=[]): array
{
    $context=mg_creator_campaign_payout_merchant_context($pdo,$user,'merchant.creator_payouts.view');mg_creator_campaign_operations_assert_installed($pdo);
    $workspaceId=(int)$context['workspace_id'];$currency=mg_creator_campaign_operations_currency($filters['currency']??'USD');
    $policy=mg_creator_campaign_operations_effective_policy($pdo,$workspaceId,$currency);
    $readiness=mg_creator_campaign_operations_campaign_readiness($pdo,$workspaceId);
    $participants=mg_creator_campaign_operations_participants($pdo,$workspaceId,$currency);
    $cases=mg_creator_campaign_operations_cases($pdo,$workspaceId,$filters);
    $metrics=['campaigns'=>count($readiness),'ready_campaigns'=>0,'active_cases'=>0,'critical_cases'=>0,'eligible_creators'=>0,'committed_minor'=>0];
    foreach($readiness as $campaign)if(!empty($campaign['ready']))$metrics['ready_campaigns']++;
    foreach($cases as $case){if(in_array($case['status'],['open','acknowledged'],true))$metrics['active_cases']++;if($case['severity']==='critical'&&in_array($case['status'],['open','acknowledged'],true))$metrics['critical_cases']++;}
    foreach($participants as $participant){if($participant['payout_profile_status']==='eligible')$metrics['eligible_creators']++;$metrics['committed_minor']+=(int)$participant['committed_minor'];}
    return ['currency'=>$currency,'policy'=>$policy,'metrics'=>$metrics,'campaigns'=>$readiness,'participants'=>$participants,'cases'=>$cases,'payout_boundary'=>['provider_neutral'=>true,'manual_approval_required'=>true,'external_provider_reference_required'=>true]];
}
