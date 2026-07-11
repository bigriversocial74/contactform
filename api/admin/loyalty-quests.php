<?php
declare(strict_types=1);

require_once __DIR__ . '/_loyalty_quest_operations.php';

$method=strtoupper((string)($_SERVER['REQUEST_METHOD']??'GET'));
$admin=mg_require_permission('campaign.manage');
$adminId=(int)$admin['id'];
$pdo=mg_db();

if($method==='GET'){
    try{
        $status=strtolower(trim((string)($_GET['status']??'all')));
        $query=trim((string)($_GET['q']??''));
        if(mb_strlen($query)>180)mg_fail('Search is too long.',422);
        $campaignResult=mg_lqo_campaigns($pdo,$status,$query,150);
        $evidence=mg_lqo_evidence_queue($pdo,100);
        $deliveries=mg_lqo_delivery_queue($pdo,100);
        $summary=mg_lqo_summary($campaignResult['items'],$evidence,$deliveries);
        header('Cache-Control: private, no-store, max-age=0');
        mg_ok([
            'summary'=>$summary,
            'campaigns'=>$campaignResult['items'],
            'evidence'=>$evidence,
            'deliveries'=>$deliveries,
            'recent_actions'=>mg_lqo_recent_events($pdo,50),
            'delivery_ready'=>$campaignResult['delivery_ready'],
            'authority'=>[
                'can_pause_resume_end'=>true,
                'can_nudge_review'=>true,
                'can_retry_delivery'=>true,
                'can_approve_evidence'=>false,
                'can_issue_rewards'=>false,
                'can_redeem_pppm'=>false,
            ],
        ],'Loyalty Quest admin operations loaded.');
    }catch(Throwable $error){
        mg_security_log('error','admin.loyalty_quest_operations_load_failed','Unable to load Loyalty Quest admin operations.',['exception_class'=>$error::class],$adminId);
        mg_fail('Unable to load Loyalty Quest admin operations.',500);
    }
}

if($method!=='POST')mg_fail('Method not allowed.',405);
$input=mg_input();mg_require_csrf_for_write($input);
$action=strtolower(trim((string)($input['action']??'')));
try{
    $reason=mg_lqo_require_reason($input);
    $pdo->beginTransaction();
    if(in_array($action,['pause','resume','end'],true)){
        $campaignRef=strtolower(trim((string)($input['campaign_id']??'')));
        if(strlen($campaignRef)!==36||preg_match('/^[a-f0-9-]{36}$/',$campaignRef)!==1)throw new InvalidArgumentException('Invalid Loyalty Quest.');
        $result=mg_lqo_campaign_action($pdo,$adminId,$campaignRef,$action,$reason);
        $message='Loyalty Quest '.($action==='pause'?'paused':($action==='resume'?'resumed':'ended')).'.';
    }elseif($action==='review_nudge'){
        $evidenceRef=strtolower(trim((string)($input['evidence_id']??'')));
        if(strlen($evidenceRef)!==36||preg_match('/^[a-f0-9-]{36}$/',$evidenceRef)!==1)throw new InvalidArgumentException('Invalid quest evidence.');
        $result=mg_lqo_review_nudge($pdo,$adminId,$evidenceRef,$reason);
        $message='Merchant review reminder created.';
    }elseif($action==='retry_delivery'){
        $jobRef=strtolower(trim((string)($input['job_id']??'')));
        if(strlen($jobRef)!==36||preg_match('/^[a-f0-9-]{36}$/',$jobRef)!==1)throw new InvalidArgumentException('Invalid delivery job.');
        $result=mg_lqo_retry_delivery($pdo,$adminId,$jobRef,$reason);
        $message='Delivery job queued for retry.';
    }else{
        throw new InvalidArgumentException('Invalid Loyalty Quest admin action.');
    }
    $pdo->commit();
    mg_ok($result,$message);
}catch(InvalidArgumentException $error){if($pdo->inTransaction())$pdo->rollBack();mg_fail($error->getMessage(),422);}catch(DomainException $error){if($pdo->inTransaction())$pdo->rollBack();mg_fail($error->getMessage(),409);}catch(RuntimeException $error){if($pdo->inTransaction())$pdo->rollBack();mg_fail($error->getMessage(),404);}catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();mg_security_log('error','admin.loyalty_quest_operations_action_failed','Unable to complete Loyalty Quest admin action.',['action'=>$action,'exception_class'=>$error::class],$adminId);mg_fail('Unable to complete Loyalty Quest admin action.',500);}
